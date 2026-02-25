# Power BI RLS - Quick Setup Guide

## ⚡ Quick Setup (5 Minutes)

### 1️⃣ Open Power BI Desktop
Open your report file: `Sales_Report.pbix`

---

### 2️⃣ Create 4 RLS Roles

Go to: **Modeling Tab** → **Manage Roles** → **Create**

#### Role: `National`
```dax
(Leave blank - no filter)
```

#### Role: `KHI`
```dax
[OU_ID] IN (102, 103, 104, 105, 106)
```

#### Role: `LHR`
```dax
[OU_ID] IN (108, 109)
```

#### Role: `Salesperson` ⭐
```dax
[Salesperson name] = USERPRINCIPALNAME()
```
**Replace `[Salesperson name]` with YOUR column name!**

---

### 3️⃣ Test Before Publishing

**Modeling** → **View as Roles** → Select **Salesperson**

✅ Check "Other user" → Enter: `Tajammul Ahmed`

Expected: Only Tajammul's data shows

---

### 4️⃣ Publish

Click **Publish** → Select your workspace → Done!

---

## 🔍 Find Your Column Name

Not sure what your salesperson column is called?

**In Power BI Desktop:**
1. Click on your Sales table
2. Look for columns like:
   - `Salesperson name` ✅
   - `SalespersonName`
   - `Sales Person`
   - `Rep Name`
   - `Employee Name`

**Or use this DAX in New Measure:**
```dax
Test = CONCATENATEX(
    TOPN(1, DISTINCT(YourTable[YourSalespersonColumn])),
    YourTable[YourSalespersonColumn],
    ", "
)
```

---

## ✅ Verification Checklist

- [ ] 4 roles created (National, KHI, LHR, Salesperson)
- [ ] Salesperson role uses correct column name
- [ ] Tested with "View as Roles" → Works correctly
- [ ] Published to Power BI Service
- [ ] Laravel shows correct badge on login
- [ ] Tajammul Ahmed sees only his data

---

## 📝 Important Reminders

1. **Exact Name Match Required**
   - MySQL: `Tajammul Ahmed`
   - Power BI: `Tajammul Ahmed`
   - Must be EXACTLY the same!

2. **Don't Add Users in Power BI Service**
   - Laravel handles user assignment automatically
   - Leave the RLS role membership empty in Power BI Service

3. **Service Account**
   - Must be: `qgbi@quadrigroupcom.onmicrosoft.com`
   - Must have workspace access
   - Must have dataset permissions

---

## 🐛 Quick Troubleshooting

| Problem | Solution |
|---------|----------|
| No data showing | Check name spelling in both MySQL and Power BI |
| All data showing | RLS role not applied - check DAX filter |
| Error loading | Check service account permissions |
| Token expired | Automatic refresh in Laravel (wait 5 min) |

---

## 📞 Need Help?

Check full guide: `POWERBI_RLS_CONFIGURATION.md`

Check Laravel logs: `storage/logs/laravel.log`
