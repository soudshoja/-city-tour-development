# OpenWebUI Integration Setup Guide

This document explains how to set up and use the OpenWebUI integration for AI file processing.

## Configuration

Add the following environment variables to your `.env` file:

```env
# Set the AI provider to use OpenWebUI
AI_PROVIDER=openwebui

# OpenWebUI Configuration
OPENWEBUI_API_KEY=your_openwebui_api_key_here
OPENWEBUI_API_URL=http://localhost:3000
OPENWEBUI_MODEL=llama3.1:latest
```

## Environment Variables

- `AI_PROVIDER`: Set to `openwebui` to use the OpenWebUI client
- `OPENWEBUI_API_KEY`: Your OpenWebUI API key
- `OPENWEBUI_API_URL`: The base URL of your OpenWebUI instance (default: http://localhost:3000)
- `OPENWEBUI_MODEL`: The model to use for processing (e.g., llama3.1:latest, gpt-4, etc.)

## How It Works

The OpenWebUI integration implements a new file processing workflow that includes:

1. **File Upload**: Files are uploaded to OpenWebUI using the `/v1/files/` endpoint
2. **RAG Processing**: The system waits for the file to be indexed for Retrieval-Augmented Generation (RAG)
3. **Data Extraction**: Uses the chat completions endpoint with file references to extract structured data

## Key Features

- **Seamless Integration**: Implements the same `AIClientInterface` as other providers
- **RAG Support**: Leverages OpenWebUI's document indexing capabilities
- **File Type Support**: Handles PDFs, text files, and other document formats
- **Batch Processing**: Supports processing multiple files at once
- **Error Handling**: Comprehensive error handling and logging

## Usage

Once configured, the system will automatically use OpenWebUI for file processing when you run:

```bash
php artisan app:process-files
```

### Switching Between Providers

You can switch between AI providers without affecting your existing code:

```env
# Use OpenAI
AI_PROVIDER=openai

# Use OpenWebUI
AI_PROVIDER=openwebui

# Use AnythingLLM
AI_PROVIDER=anythingllm
```

## Process Flow Comparison

### Traditional Process (OpenAI)
1. Upload file to OpenAI
2. Send prompt with file reference
3. Process response
4. Clean up file

### New Process (OpenWebUI)
1. Upload file to OpenWebUI
2. Wait for RAG indexing to complete
3. Send extraction prompt with file reference in RAG context
4. Process response
5. Clean up file

## Benefits of OpenWebUI Integration

1. **Local Processing**: Can run entirely on your own infrastructure
2. **Cost Control**: No per-token charges like with cloud providers
3. **Privacy**: Documents stay within your environment
4. **RAG Capabilities**: Better document understanding through indexing
5. **Model Flexibility**: Use any supported local or remote model

## Troubleshooting

### Common Issues

1. **Connection Errors**: Verify `OPENWEBUI_API_URL` is correct and OpenWebUI is running
2. **Authentication Errors**: Check that `OPENWEBUI_API_KEY` is valid
3. **File Processing Timeout**: Large files may take longer to index - adjust timeout if needed
4. **Model Not Found**: Ensure the specified model is available in your OpenWebUI instance

### Logging

The system logs all OpenWebUI interactions to the AI log channel. Check your logs for detailed information about file processing status.

## Migration from Other Providers

To migrate from OpenAI or other providers to OpenWebUI:

1. Set up your OpenWebUI instance
2. Update your `.env` configuration
3. Test with a few files first
4. The existing `ProcessAirFiles` command will work without code changes

## Example Configuration

Here's a complete example configuration:

```env
# AI Provider Configuration
AI_PROVIDER=openwebui

# OpenWebUI Settings
OPENWEBUI_API_KEY=owui_12345_abcdef
OPENWEBUI_API_URL=http://127.0.0.1:8080
OPENWEBUI_MODEL=llama3.1:8b

# Optional: Fallback to OpenAI if needed
OPENAI_API_KEY=sk-...
OPENAI_API_URL=https://api.openai.com/v1
OPENAI_MODEL=gpt-4
```

This allows you to quickly switch between providers by changing just the `AI_PROVIDER` value.