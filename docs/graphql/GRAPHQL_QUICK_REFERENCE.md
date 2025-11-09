# GraphQL Hotel Search - Quick Reference

## 🚀 Quick Start

### 1. Test GraphQL is Working
```bash
curl -X POST http://localhost/graphql \
  -H "Content-Type: application/json" \
  -d '{"query":"{ __schema { queryType { name } } }"}'
```

### 2. Validate Schema
```bash
php artisan lighthouse:validate-schema
```

### 3. Clear Caches
```bash
php artisan lighthouse:clear-cache && php artisan config:clear && php artisan cache:clear
```

---

## 📋 GraphQL Query Templates

### Minimal Query (Copy & Paste)
```graphql
query {
  searchHotelRooms(input: {
    telephone: "+96512345678"
    hotel: "HOTEL_NAME_HERE"
    checkIn: "2025-12-01"
    checkOut: "2025-12-05"
  }) {
    success
    message
    data {
      hotel_name
      room {
        room_name
        price
        currency
      }
      prebook {
        availability_token
      }
    }
  }
}
```

### Full Query (All Fields)
```graphql
query {
  searchHotelRooms(input: {
    telephone: "+96512345678"
    hotel: "HOTEL_NAME_HERE"
    checkIn: "2025-12-01"
    checkOut: "2025-12-05"
  }) {
    success
    message
    data {
      telephone
      hotel_name
      srk
      enquiry_id
      hotel_index
      offer_index
      result_token
      room {
        id
        room_name
        board_basis
        non_refundable
        price
        currency
        room_token
        package_token
        occupancy
      }
      prebook {
        availability_token
        check_in
        check_out
        duration
        autocancel_date
        cancel_policy
        remarks
      }
    }
  }
}
```

---

## 🔍 Helpful SQL Queries

### Find Hotel Names
```sql
SELECT id, name, city_id 
FROM map_hotels 
WHERE name LIKE '%Hilton%' 
ORDER BY name 
LIMIT 10;
```

### Find Agent Phone Numbers
```sql
SELECT a.id, a.phone_number, a.country_code, b.company_id 
FROM agents a 
JOIN branches b ON a.branch_id = b.id 
LIMIT 10;
```

### Check Recent Searches
```sql
SELECT * 
FROM temporary_offers 
WHERE telephone = '+96512345678' 
ORDER BY created_at DESC 
LIMIT 5;
```

### Check Offered Rooms
```sql
SELECT or.*, to.hotel_name 
FROM offered_rooms or
JOIN temporary_offers to ON or.temp_offer_id = to.id
WHERE to.telephone = '+96512345678'
ORDER BY or.price ASC
LIMIT 10;
```

---

## 🐛 Debugging Commands

### Check GraphQL Route
```bash
php artisan route:list | grep graphql
```

### Test Database Connection
```bash
php artisan tinker
>>> DB::connection('mysql_map')->table('hotels')->count()
```

### Check Logs
```bash
tail -f storage/logs/magic_holidays/magic_holidays.log
tail -f storage/logs/magic_holidays/magic_holidays_error.log
tail -f storage/logs/laravel.log
```

### Clear Everything
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan lighthouse:clear-cache
composer dump-autoload
```

---

## 📡 API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/graphql` | POST | Main GraphQL endpoint |
| `/graphql-playground` | GET | GraphQL Playground UI |
| `/graphiql` | GET | GraphiQL IDE (if enabled) |

---

## 🔑 Environment Check

```bash
# Check Magic Holiday credentials
php artisan tinker
>>> config('services.magic-holiday')
```

---

## 📦 File Locations

### Core Files
- Schema: `graphql/schema.graphql`
- Service: `app/Services/HotelSearchService.php`
- Resolver: `app/GraphQL/Queries/SearchHotelRooms.php`
- Magic API: `app/Services/MagicHolidayService.php`

### Config
- Lighthouse: `config/lighthouse.php`
- Services: `config/services.php`

### Logs
- Main: `storage/logs/magic_holidays/magic_holidays.log`
- Errors: `storage/logs/magic_holidays/magic_holidays_error.log`

---

## 🧪 Test Scenarios

### 1. Valid Search
- Phone: Agent's actual phone number
- Hotel: Exact name from database
- Dates: Future dates (tomorrow or later)
- Expected: Success with room data

### 2. Unknown Phone
- Phone: Random number
- Expected: Uses default credentials, may succeed if hotel available

### 3. Invalid Hotel
- Hotel: Non-existent name
- Expected: `success: false`, message "Hotel not found"

### 4. Invalid Dates
- CheckOut before CheckIn
- Expected: Validation error

### 5. Past Dates
- CheckIn: Yesterday
- Expected: Validation error

---

## 🔄 Common Tasks

### Update Schema
1. Edit `graphql/schema.graphql`
2. Run `php artisan lighthouse:validate-schema`
3. Run `php artisan lighthouse:clear-cache`

### Add New Query
1. Add to `graphql/schema.graphql`
2. Create resolver in `app/GraphQL/Queries/`
3. Validate and clear cache

### Change Response Fields
1. Edit types in `graphql/schema.graphql`
2. Update service methods if needed
3. Validate and clear cache

---

## 🎯 Success Indicators

✅ Schema validates without errors  
✅ GraphQL endpoint responds (even with errors)  
✅ Logs show API calls to Magic Holiday  
✅ Temporary offers saved to database  
✅ Response includes availability_token  

---

## ⚡ Performance Tips

- Magic Holiday search can take **30-120 seconds**
- Progress polling: 60 attempts × 2 seconds = **2 min max**
- Rate limits apply (handled automatically)
- Consider caching for frequently searched hotels

---

## 📞 Quick Troubleshooting

### "Schema validation failed"
→ Check `graphql/schema.graphql` syntax

### "Hotel not found"
→ Use exact hotel name from database

### "Magic Holiday search timeout"
→ Check API connectivity, increase timeout

### "No availability"
→ Try different dates, check Magic Holiday status

### GraphQL 500 error
→ Check `storage/logs/laravel.log`

---

## 🎓 Learn More

- Lighthouse Docs: https://lighthouse-php.com
- GraphQL Spec: https://graphql.org
- Magic Holiday API: https://www.magicholidays.net/reseller/api/hotels/v1

---

**Ready to test? Copy a query template above and replace placeholders!** 🚀
