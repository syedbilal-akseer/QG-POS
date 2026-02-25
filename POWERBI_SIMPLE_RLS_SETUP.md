# Power BI RLS - Simple Setup (ONE Role Only!)

## Simplified Approach ✅

Use **ONE role** (`National`) for everyone. Filtering happens by passing different **effective identities**.

---

## How It Works

### Power BI Side (ONE Role)

**Role Name**: `National`

**DAX Filter**:
```dax
[Sales Person Hierarchy][Name] = USERPRINCIPALNAME()
```

### Laravel Side (Pass Different Identities)

| User Type | RLS Role | Effective Identity | Result |
|-----------|----------|-------------------|--------|
| **Admin** | National | `qgbi@quadrigroupcom.onmicrosoft.com` | Email doesn't match any [Name] → **Shows ALL data** |
| **Sales Head** | National | `qgbi@quadrigroupcom.onmicrosoft.com` | Email doesn't match any [Name] → **Shows ALL data** |
| **Tajammul Ahmed** | National | `"Tajammul Ahmed"` | Matches [Name] = "Tajammul Ahmed" → **Shows ONLY his data** |
| **Other Salesperson** | National | `"Their Name"` | Matches their name → **Shows ONLY their data** |

---

## Power BI Setup (3 Steps!)

### Step 1: Open Power BI Desktop
Open your `.pbix` file

### Step 2: Create ONE RLS Role

**Modeling** → **Manage Roles** → **Create**

- **Role Name**: `National`
- **Table**: `Sales Person Hierarchy` (based on your structure file)
- **Column**: `Name`
- **DAX Filter**:

```dax
[Sales Person Hierarchy][Name] = USERPRINCIPALNAME()
```

**Explanation**:
- When `USERPRINCIPALNAME()` = `"Tajammul Ahmed"` → Shows only his data
- When `USERPRINCIPALNAME()` = `"qgbi@quadrigroupcom.onmicrosoft.com"` → Doesn't match any name → Shows all data

### Step 3: Test

**Modeling** → **View as Roles**

**Test 1**: Salesperson
- Role: `National`
- Other user: `Tajammul Ahmed`
- **Expected**: Only Tajammul's data

**Test 2**: Admin (All Data)
- Role: `National`
- Other user: `qgbi@quadrigroupcom.onmicrosoft.com`
- **Expected**: ALL data (because email doesn't match any salesperson name)

### Step 4: Publish
Click **Publish** → Done!

---

## Laravel Configuration (Already Done ✅)

**File**: `/mnt/d/pos/app/Http/Controllers/Admin/BIDashboardController.php`

### Everyone Gets National Role:
```php
protected function getUserRlsRoles($user): array
{
    return ['National'];  // Everyone uses the same role
}
```

### Effective Identity Differs:
```php
protected function getEffectiveIdentityEmail($user): string
{
    // Salespeople: Pass their name
    if ($user->isSalesPerson() || $user->isHOD() || $user->isManager()) {
        return $user->name;  // "Tajammul Ahmed"
    }

    // Admins: Pass service account (won't match any name)
    return 'qgbi@quadrigroupcom.onmicrosoft.com';
}
```

---

## How the Filter Works

### Example 1: Tajammul Ahmed Logs In

**Laravel sends to Power BI**:
```json
{
  "roles": ["National"],
  "username": "Tajammul Ahmed"
}
```

**Power BI evaluates**:
```dax
[Sales Person Hierarchy][Name] = "Tajammul Ahmed"
```

**Result**: Only rows where `[Name] = "Tajammul Ahmed"` are shown ✅

### Example 2: Admin Logs In

**Laravel sends to Power BI**:
```json
{
  "roles": ["National"],
  "username": "qgbi@quadrigroupcom.onmicrosoft.com"
}
```

**Power BI evaluates**:
```dax
[Sales Person Hierarchy][Name] = "qgbi@quadrigroupcom.onmicrosoft.com"
```

**Result**: No rows match this name → Filter returns FALSE for all rows → DAX shows **ALL data** ✅

---

## Testing After Setup

### 1. Clear Laravel Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### 2. Test As Tajammul Ahmed

**Login** → **BI Dashboard** → **F12 Console**:
```
Power BI RLS Configuration: username=Tajammul Ahmed, roles=['National']
```

**Expected**:
- Badge: "Personal View - Tajammul Ahmed" (orange)
- Data: Only Tajammul Ahmed's sales

### 3. Test As Admin

**Login** → **BI Dashboard** → **F12 Console**:
```
Power BI RLS Configuration: username=qgbi@quadrigroupcom.onmicrosoft.com, roles=['National']
```

**Expected**:
- Badge: "National View" (blue)
- Data: ALL salespeople data

---

## Advantages of This Approach

✅ **Only ONE role to manage** - No separate "Salesperson" role needed
✅ **Simpler Power BI configuration** - Just one DAX filter
✅ **Easier to maintain** - Single source of truth
✅ **Works for all users** - Same role, different identities
✅ **No role assignment needed** - Laravel handles everything

---

## Troubleshooting

### Issue: Salesperson sees all data

**Check 1**: Is the DAX filter correct?
```dax
[Sales Person Hierarchy][Name] = USERPRINCIPALNAME()
```

**Check 2**: Does the table/column exist?
- Table: `Sales Person Hierarchy`
- Column: `Name`

**Check 3**: Do names match exactly?
- MySQL: `Tajammul Ahmed`
- Power BI: `Tajammul Ahmed`

### Issue: Admin sees no data

**Problem**: The filter is too restrictive

**Solution**: Make sure the DAX filter allows non-matching identities to see all data. The current filter does this automatically because:
- Service account email = `"qgbi@quadrigroupcom.onmicrosoft.com"`
- This doesn't match any salesperson name
- DAX evaluates to FALSE for all rows
- Power BI interprets this as "show all" for admin users

**Alternative DAX (if needed)**:
```dax
[Sales Person Hierarchy][Name] = USERPRINCIPALNAME()
|| USERPRINCIPALNAME() = "qgbi@quadrigroupcom.onmicrosoft.com"
```

This explicitly says: "Show data where Name matches, OR if user is the service account"

---

## Summary

### Power BI (ONE role):
```dax
Role: National
Filter: [Sales Person Hierarchy][Name] = USERPRINCIPALNAME()
```

### Laravel (Pass different usernames):
- Tajammul Ahmed → `"Tajammul Ahmed"` → Sees only his data
- Admin → `"qgbi@quadrigroupcom.onmicrosoft.com"` → Sees all data

**That's it!** Simple and effective. 🎯

---

## Quick Checklist

- [ ] Power BI: Create `National` role
- [ ] Power BI: Add DAX filter: `[Sales Person Hierarchy][Name] = USERPRINCIPALNAME()`
- [ ] Power BI: Test with "View as Roles"
- [ ] Power BI: Publish to service
- [ ] Laravel: Clear cache
- [ ] Browser: Hard refresh (`Ctrl + Shift + R`)
- [ ] Test: Tajammul Ahmed sees only his data
- [ ] Test: Admin sees all data
- [ ] Done! ✅
