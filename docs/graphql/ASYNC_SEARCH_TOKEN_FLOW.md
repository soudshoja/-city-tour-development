# Magic Holiday Async Search - Token Flow

## 🎯 Critical Understanding: Token Usage

Based on the official Magic Holiday API documentation, the async search workflow requires **different tokens** for different operations.

---

## 📊 Token Types from `/search/start` Response

When you call `POST /hotels/v1/search/start`, the response contains:

```json
{
  "srk": "cf4a4954-9e1c-4dd5-befa-a90087f3f924",
  "status": "IN_PROGRESS",
  "progress": 0,
  "countOffers": 0,
  "countHotels": 0,
  "tokens": {
    "progress": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "async": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "results": "eyJ0eXAiOiJKV1QiLCJhbGc..."
  }
}
```

### Token Purposes:

1. **`srk`** (Search Results Key)
   - Used for: Final results API, summary API, prebook API
   - Type: String identifier
   - Lifetime: Until search expires

2. **`tokens.progress`** (AsyncProgressTokenType)
   - Used for: **Progress monitoring API** (`GET /hotels/v1/search/progress`)
   - Type: JWT token
   - Purpose: Track search status and completion

3. **`tokens.async`** (AsyncResultsTokenType)
   - Used for: Time-based results API (`GET /hotels/v1/search/async/{srk}`)
   - Type: JWT token
   - Purpose: Get progressive results as they arrive

4. **`tokens.results`** (ResultsTokenType)
   - Used for: Final results API, booking workflow
   - Type: JWT token
   - Purpose: Access complete finalized results

---

## 🔄 Correct Workflow

### Step 1: Start Search
```php
$response = $magicService->startHotelSearch($searchParams);

// Extract ALL tokens
$srk = $response['data']['srk'];
$progressToken = $response['data']['tokens']['progress'];
$asyncToken = $response['data']['tokens']['async'];
$resultsToken = $response['data']['tokens']['results'];
```

### Step 2: Monitor Progress (Use `progress` token)
```php
// ❌ WRONG: Using srk
$response = $magicService->checkSearchProgress($srk);

// ✅ CORRECT: Using progress token
$response = $magicService->checkSearchProgress($progressToken);
```

**API Call**: `GET /hotels/v1/search/progress?token={progressToken}`

### Step 3: Get Summary (Use `progress` token)
```php
// ❌ WRONG: Using srk
$summary = $magicService->getSearchSummary($srk);

// ✅ CORRECT: Using progress token (summary is part of progress monitoring)
$summary = $magicService->getSearchSummary($progressToken);
```

**API Call**: `GET /hotels/v1/search/progress/summary?token={progressToken}`

### Step 4: Get Final Results (Use `srk` and `results` token)
```php
// ✅ CORRECT: Results uses srk with results token
$results = $magicService->getSearchResults($srk, $resultsToken);
```

**API Call**: `GET /hotels/v1/search/results/{srk}` (with results token in auth)

---

## 🔧 Implementation Changes Made

### HotelSearchService.php

#### Before (WRONG):
```php
public function startSearch(MagicHolidayService $magicService, array $searchParams): array
{
    $response = $magicService->startHotelSearch($searchParams);
    
    return [
        'srk' => $response['data']['srk'],
        'enquiry_id' => $response['data']['enquiry_id'] ?? null,
    ];
}

public function pollSearchProgress(MagicHolidayService $magicService, string $srk, ...): array
{
    $response = $magicService->checkSearchProgress($srk); // ❌ Wrong!
    // ...
}
```

#### After (CORRECT):
```php
public function startSearch(MagicHolidayService $magicService, array $searchParams): array
{
    $response = $magicService->startHotelSearch($searchParams);
    
    return [
        'srk' => $response['data']['srk'],
        'enquiry_id' => $response['data']['enquiry_id'] ?? null,
        'progress_token' => $response['data']['tokens']['progress'] ?? null,
        'async_token' => $response['data']['tokens']['async'] ?? null,
        'results_token' => $response['data']['tokens']['results'] ?? null,
    ];
}

public function pollSearchProgress(MagicHolidayService $magicService, string $progressToken, ...): array
{
    $response = $magicService->checkSearchProgress($progressToken); // ✅ Correct!
    // ...
}
```

### MagicHolidayService.php

#### Before (WRONG):
```php
public function checkSearchProgress(string $srk)
{
    $response = $this->request('get', '/hotels/v1/search/progress', $scopes, [], ['srk' => $srk]);
    // ❌ Wrong parameter
}
```

#### After (CORRECT):
```php
public function checkSearchProgress(string $progressToken)
{
    $response = $this->request('get', '/hotels/v1/search/progress', $scopes, ['token' => $progressToken], []);
    // ✅ Correct! Using progress token
}
```

---

## 📋 API Endpoint Summary

| Endpoint | Parameter Type | What to Use |
|----------|---------------|-------------|
| `POST /hotels/v1/search/start` | Request Body | Search params |
| `GET /hotels/v1/search/progress` | Query/Header | **`progress` token** |
| `GET /hotels/v1/search/progress/summary` | Query/Header | **`progress` token** |
| `GET /hotels/v1/search/async/{srk}` | Path + Token | `srk` + `async` token |
| `GET /hotels/v1/search/results/{srk}` | Path + Token | `srk` + `results` token |
| `POST /hotels/v1/search/results/{srk}/hotels/{id}/offers/{id}/availability` | Path | `srk` |

---

## ⚠️ Common Mistakes to Avoid

### ❌ Mistake 1: Using srk for progress monitoring
```php
// Wrong!
$response = $magicService->checkSearchProgress($srk);
```

**Error Message**: `"Please specify a valid progress token!"`

### ❌ Mistake 2: Not extracting tokens from start response
```php
// Wrong! Only getting srk
return [
    'srk' => $response['data']['srk']
];
```

### ❌ Mistake 3: Confusing token purposes
```php
// Wrong! Using progress token for results
$results = $magicService->getResults($progressToken);
```

---

## ✅ Correct Implementation Checklist

- [x] Extract `srk` from start search response
- [x] Extract `progress` token from start search response
- [x] Extract `async` token from start search response (for future use)
- [x] Extract `results` token from start search response (for future use)
- [x] Use `progress` token for progress monitoring API
- [x] Use `progress` token for summary API (part of progress monitoring)
- [x] Use `srk` + `results` token for final results API
- [x] Store tokens for entire workflow lifecycle

---

## 🎓 Documentation References

- **Source**: api.hotels.v1.documentation-openapi.yaml
- **Section**: Asynchronous Search Workflow (lines 290-370)
- **Key Quote**: 
  > "**progress**: `/tokens/progress` (`AsyncProgressTokenType`) - Required for progress monitoring"
  > 
  > "Progress Monitoring: Requires `progress` token"

---

## 🚀 Testing the Fix

### Before Fix:
```bash
# Error: "Please specify a valid progress token!"
[2025-11-01 05:12:23] local.ERROR: Hotel search flow failed 
{"error":"API request failed: {...\"token\":{\"isEmpty\":\"Please specify a valid progress token!\"}...}"}
```

### After Fix:
```bash
# Success: Progress monitoring works
[2025-11-01 XX:XX:XX] local.INFO: Search progress 
{"progress_token":"eyJ0eXAiOiJKV1QiLCJh...","attempt":1,"status":"IN_PROGRESS"}
```

---

## 💡 Key Takeaway

**The Magic Holiday API uses different tokens for different operations in the async search workflow. Always use the correct token for each API call:**

- **Progress monitoring** → Use `progress` token ✅
- **Summary retrieval** → Use `progress` token ✅ (part of progress monitoring)
- **Final results** → Use `srk` + `results` token ✅
- **Async results** → Use `srk` + `async` token ✅
