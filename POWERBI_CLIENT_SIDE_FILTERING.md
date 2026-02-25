# Power BI Client-Side Filtering Setup

## Overview
This system gets **ALL raw data** from Power BI and filters it **client-side** using JavaScript based on the logged-in user's role.

---

## How It Works

### 1. Power BI Side (Simple!)
- **Only 1 RLS Role needed**: `National` (no filter, returns all data)
- Everyone gets ALL data from Power BI
- No complex RLS configuration needed

### 2. Laravel Side (✅ Already Done)
- Determines user's filter requirements:
  - **Admin/Sales Head**: No filter (see all)
  - **CMD-KHI**: Filter to Karachi (OU: 102-106)
  - **CMD-LHR**: Filter to Lahore (OU: 108-109)
  - **Salespeople**: Filter to their name (e.g., "Tajammul Ahmed")

### 3. Frontend JavaScript (✅ Already Done)
- Applies filters after report loads
- Uses Power BI JavaScript API `setFilters()` method
- Automatically tries common column names

---

## Power BI Setup (2 Steps Only!)

### Step 1: Create ONE RLS Role

**In Power BI Desktop:**
1. Go to **Modeling** tab
2. Click **Manage Roles**
3. Create role: `National`
4. **Leave filter BLANK** (no DAX filter)
5. Click **Save**

That's it! No complex RLS needed.

### Step 2: Publish to Power BI Service

1. Click **Publish**
2. Select your workspace
3. Done!

---

## What Happens for Each User Type

### Admin User Login
```
MySQL: role = 'admin'
Laravel Filter: type = 'none'
JavaScript: No filter applied
Result: Sees ALL data
Badge: "National View" (blue)
```

### Tajammul Ahmed (Salesperson) Login
```
MySQL: name = 'Tajammul Ahmed', role = 'user'
Laravel Filter: type = 'salesperson', salespersonName = 'Tajammul Ahmed'
JavaScript: Applies filter [Salesperson name] = 'Tajammul Ahmed'
Result: Sees ONLY his data
Badge: "Personal View - Tajammul Ahmed" (orange)
```

### CMD-KHI User Login
```
MySQL: role = 'cmd-khi'
Laravel Filter: type = 'location', ouIds = [102,103,104,105,106]
JavaScript: Applies filter [OU_ID] IN (102,103,104,105,106)
Result: Sees ONLY Karachi data
Badge: "National View" (currently, will show KHI badge)
```

---

## Column Names in Power BI Dataset

The JavaScript will automatically try these column names:

### For Salesperson Filtering:
- `Salesperson name` ✅
- `SalespersonName`
- `Sales Person`
- `Rep Name`
- `Employee Name`
- `Full Name`
- `Name`

### For Location Filtering:
- `OU_ID` ✅ (recommended)
- `Location`

**If your columns have different names**, update the table name and column names in:
`resources/views/admin/bi-dashboard.blade.php` lines 288 and 307

---

## Advantages of This Approach

✅ **Simple Power BI setup** - Only 1 RLS role needed
✅ **Flexible filtering** - Easily change filters in Laravel
✅ **Better debugging** - Console logs show filter application
✅ **No email matching** - Works with names directly
✅ **Multi-column fallback** - Tries multiple column names automatically
✅ **Dynamic updates** - Change filters without republishing Power BI

---

## Testing Checklist

### Power BI Desktop
- [ ] Create `National` role with no filter
- [ ] Publish to Power BI Service

### Laravel Application
- [ ] Login as Admin → See "National View" badge
- [ ] Login as Tajammul Ahmed → See "Personal View - Tajammul Ahmed" badge
- [ ] Open browser console (F12) → Check for logs:
  ```
  Applying user filters: {type: "salesperson", salespersonName: "Tajammul Ahmed"}
  Filtering by salesperson: Tajammul Ahmed
  Filter applied successfully: Salesperson name
  ```

### Data Verification
- [ ] Admin sees all salespeople data
- [ ] Tajammul Ahmed sees only his sales
- [ ] CMD-KHI sees only Karachi sales
- [ ] CMD-LHR sees only Lahore sales

---

## Debugging

### Check Browser Console (F12)
Look for these logs:
```javascript
Power BI Report loaded successfully
Power BI Report rendered
Applying user filters: {type: "salesperson", salespersonName: "Tajammul Ahmed"}
Filtering by salesperson: Tajammul Ahmed
Filter applied successfully: Salesperson name
```

### Check Laravel Logs
```bash
tail -f storage/logs/laravel.log
```

Look for:
```
Power BI RLS Configuration: username=qgbi@..., roles=['National']
```

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| No filtering applied | Column name mismatch | Check column names in console logs |
| All data visible | Filter not applied | Check console for errors |
| Error in console | Table name wrong | Update table name in blade file (line 288) |

---

## Customization

### Update Table Name
If your Power BI table is NOT called "Sales":

Edit: `resources/views/admin/bi-dashboard.blade.php`
```javascript
// Line 288 and 307
target: {
    table: "YourTableName",  // Change this
    column: columnName
}
```

### Add More Column Name Attempts
Edit: `resources/views/admin/bi-dashboard.blade.php`
```javascript
// Line 274
const possibleColumns = [
    'Salesperson name',
    'YourCustomColumnName',  // Add your column name here
    // ...
];
```

---

## Summary

**Power BI**: Just create `National` role (no filter)
**Laravel**: Determines what data user should see
**JavaScript**: Applies the filter client-side

Simple, flexible, and easy to debug! 🎉
