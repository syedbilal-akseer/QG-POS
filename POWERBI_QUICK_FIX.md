# Power BI RLS - Quick Fix Reference

## ⚡ Quick Start

**Problem**: Everyone sees all data instead of their own data.

**Laravel Status**: ✅ Working correctly
**Power BI Status**: ❌ Needs configuration

---

## 🔧 5-Minute Fix

### Step 1: Diagnostic (1 min)
Go to: `/admin/bi-dashboard/diagnostic`

Check:
- Effective Identity value
- Expected behavior section

### Step 2: Power BI Desktop (2 min)

1. Open your `.pbix` file
2. **Modeling** → **Manage roles**
3. Create role: `National`
4. Select table: `DIM_SALESREP`
5. DAX filter:
   ```dax
   [Name] = USERPRINCIPALNAME()
   ```
6. **Modeling** → **View as** → Check "National" → Type: `Tajammul Ahmed`
7. Verify: Should see only Tajammul's data ✅

### Step 3: Publish (1 min)

1. **File** → **Publish** → **Publish to Power BI**
2. Select your workspace
3. Wait for completion

### Step 4: Test (1 min)

1. Go to: `/admin/bi-dashboard/clear-cache`
2. Open: `/admin/bi-dashboard`
3. Verify: Salespeople see only their data ✅

---

## 🎯 Key Points

### Correct Table/Column Names

**Most likely names** (check your data):
- Table: `DIM_SALESREP` or `Sales Person Hierarchy`
- Column: `Name` or `SalesPersonName`

**How to verify**:
1. Power BI Desktop → **Data** view
2. Look at table list (left sidebar)
3. Click table, see column headers

### Data Format Must Match

**Laravel sends**: `"Tajammul Ahmed"`
**Power BI must have**: `"Tajammul Ahmed"` (exact match!)

**Check for**:
- ❌ Trailing spaces: `"Tajammul Ahmed "`
- ❌ Case: `"TAJAMMUL AHMED"`
- ❌ Format: `"Ahmed, Tajammul"`

### Expected Behavior

**Salespeople**: See only their data
**Admin**: See all data
**RLS Role**: Everyone uses "National"

---

## 🚨 Common Errors

### Error: "FailedToLoadModel"

**Fix**: Create the "National" role in Power BI Desktop and publish

### Error: "All data showing"

**Fix**:
1. Verify DAX filter syntax
2. Check table/column names are correct
3. Test with "View as" in Power BI Desktop

### Error: "Data format mismatch"

**Fix**: Use case-insensitive DAX:
```dax
UPPER([Name]) = UPPER(USERPRINCIPALNAME())
```

---

## 📊 Testing

**Salesperson Login**:
```
Login: Tajammul Ahmed
Expected: See only his data
Console: Effective Identity: "Tajammul Ahmed"
```

**Admin Login**:
```
Login: Admin user
Expected: See all data
Console: Effective Identity: "qgbi@quadrigroupcom.onmicrosoft.com"
```

---

## 🔗 Resources

- **Diagnostic Page**: `/admin/bi-dashboard/diagnostic`
- **Clear Cache**: `/admin/bi-dashboard/clear-cache`
- **Full Guide**: `POWERBI_RLS_FIX_GUIDE.md`
- **Changes Log**: `POWERBI_RLS_FIXES_APPLIED.md`

---

## 💡 Pro Tip

Always test RLS in Power BI Desktop using "View as" **before** publishing to Power BI Service. This saves time and ensures it works!

---

## ✅ Success Checklist

Power BI Desktop:
- [ ] "National" role exists
- [ ] DAX: `[Name] = USERPRINCIPALNAME()`
- [ ] Correct table name
- [ ] Correct column name
- [ ] "View as" test passes
- [ ] Published to Service

Power BI Service:
- [ ] Dataset security configured
- [ ] "National" role visible
- [ ] "Test as role" passes

Laravel:
- [ ] Cache cleared
- [ ] Diagnostic page checked
- [ ] Console logs verified
- [ ] Filtering works

---

**Need help?** Check the diagnostic page first!
