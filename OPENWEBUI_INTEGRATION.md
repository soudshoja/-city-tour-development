# OpenWebUI Integration Summary

## What Has Been Implemented

### 1. OpenWebUIClient Service
- **Location**: `app/AI/Services/OpenWebUIClient.php`
- **Purpose**: Implements the `AIClientInterface` to provide seamless integration with OpenWebUI
- **Key Features**:
  - Full implementation of all required interface methods
  - New file processing workflow with RAG indexing
  - Comprehensive error handling and logging
  - Support for all file types (PDF, text, AIR files)
  - Batch processing capabilities

### 2. Configuration Updates
- **Location**: `config/ai.php`
- **Changes**: Added OpenWebUI provider configuration with environment variables:
  - `OPENWEBUI_API_KEY`: Your OpenWebUI API key
  - `OPENWEBUI_API_URL`: Base URL (default: http://localhost:3000)
  - `OPENWEBUI_MODEL`: Model to use (default: llama3.1:latest)

### 3. AIManager Updates
- **Location**: `app/AI/AIManager.php`
- **Changes**: Added support for 'openwebui' provider in the factory method
- **Benefit**: No code changes needed in existing commands - they automatically use the new provider when configured

### 4. Documentation
- **Setup Guide**: `docs/openwebui-integration.md`
- **Test Script**: `tests/openwebui_test.php`
- **Management Scripts**: 
  - `scripts/manage-ai-provider.sh` (Linux/Mac)
  - `scripts/manage-ai-provider.bat` (Windows)

## Key Differences: OpenWebUI vs OpenAI Process

### OpenAI Process (Old)
```
1. Upload file → 2. Send prompt → 3. Process response → 4. Cleanup
```

### OpenWebUI Process (New)
```
1. Upload file → 2. Wait for RAG indexing → 3. Send prompt with file context → 4. Process response → 5. Cleanup
```

## How the New Process Solves Your Problem

### The Challenge
You wanted to use OpenWebUI's new file processing method that includes:
1. File upload to OpenWebUI
2. Waiting for RAG (Retrieval-Augmented Generation) processing
3. Using files in chat context for better extraction

### The Solution
The `OpenWebUIClient` implements exactly this workflow:

```php
// 1. Upload the file
$fileId = $this->uploadToOpenWebUI($file, $filename);

// 2. Wait for RAG processing
$this->waitForFileProcessing($fileId, $filename);

// 3. Extract with file context
$extractedInfo = $this->extractWithOpenWebUI($fileId, $prompt, $filename);
```

### Interface Compatibility
Since `OpenWebUIClient` implements `AIClientInterface`, your existing `ProcessAirFiles` command works without modification:

```php
// This line in ProcessAirFiles.php automatically uses OpenWebUI when configured
$extractedData = $this->aiManager->processWithAiTool($fileRealPath, $fileName);
```

## Usage Instructions

### 1. Setup OpenWebUI
Set these environment variables in your `.env`:
```env
AI_PROVIDER=openwebui
OPENWEBUI_API_KEY=your_api_key
OPENWEBUI_API_URL=http://localhost:3000
OPENWEBUI_MODEL=llama3.1:latest
```

### 2. Test the Integration
```bash
# Using the management script
./scripts/manage-ai-provider.sh setup
./scripts/manage-ai-provider.sh test

# Or using PHP
php artisan tinker
>>> include_once 'tests/openwebui_test.php';
>>> testOpenWebUIIntegration();
```

### 3. Run File Processing
```bash
# Your existing command now uses OpenWebUI
php artisan app:process-files
```

### 4. Switch Between Providers
```bash
# Switch to OpenWebUI
./scripts/manage-ai-provider.sh switch openwebui

# Switch back to OpenAI if needed
./scripts/manage-ai-provider.sh switch openai
```

## Benefits Achieved

1. **Seamless Transition**: No code changes in ProcessAirFiles command
2. **Better File Processing**: Leverages OpenWebUI's RAG capabilities
3. **Local Control**: Files processed locally without external API calls
4. **Cost Savings**: No per-token charges
5. **Flexibility**: Easy switching between providers
6. **Comprehensive Logging**: Full visibility into processing steps

## File Processing Flow in ProcessAirFiles

When you run `php artisan app:process-files`, here's what happens with OpenWebUI:

1. **File Discovery**: Command finds files in `storage/app/{company}/{supplier}/files_unprocessed/`
2. **AI Processing**: Calls `$this->aiManager->processWithAiTool($filePath, $fileName)`
3. **OpenWebUI Workflow**:
   - Upload file to OpenWebUI
   - Wait for RAG indexing (up to 2 minutes)
   - Send extraction prompt with file context
   - Parse and normalize response
4. **Task Processing**: Extract booking details and save to database
5. **File Management**: Move processed files to appropriate directories

## Error Handling

The implementation includes comprehensive error handling:
- Network timeouts and connection issues
- File processing timeouts
- Invalid responses
- Missing configuration
- File upload failures

All errors are logged to the AI log channel for debugging.

## Next Steps

1. Set up your OpenWebUI instance
2. Configure the environment variables
3. Test the integration
4. Process your files with the new system

The integration is complete and ready to use!