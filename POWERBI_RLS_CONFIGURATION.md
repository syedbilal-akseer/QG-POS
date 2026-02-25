# Power BI Row-Level Security (RLS) Configuration Guide

## Overview
This document explains how to configure Power BI RLS to filter data based on user roles and names from the Laravel MySQL database.

---

## Laravel Configuration (Already Complete ✅)

The Laravel system now passes the following to Power BI:

| User Type | RLS Role | Effective Identity | Data Access |
|-----------|----------|-------------------|-------------|
| Admin | `National` | `qgbi@quadrigroupcom.onmicrosoft.com` | All data (KHI + LHR) |
| Sales Head | `National` | `qgbi@quadrigroupcom.onmicrosoft.com` | All sales data |
| CMD-KHI | `KHI` | `qgbi@quadrigroupcom.onmicrosoft.com` | All Karachi data |
| CMD-LHR | `LHR` | `qgbi@quadrigroupcom.onmicrosoft.com` | All Lahore data |
| Salesperson (user) | `Salesperson` | **User's Name** (e.g., "Tajammul Ahmed") | Only their own data |
| HOD | `Salesperson` | **User's Name** | Only their own data |
| Line Manager | `Salesperson` | **User's Name** | Only their own data |

### Example: Tajammul Ahmed (Salesperson)
- **MySQL users.name**: `Tajammul Ahmed`
- **MySQL users.email**: `tajamul.ahmed@quadri-group.com`
- **Laravel passes to Power BI**:
  - RLS Role: `Salesperson`
  - Effective Identity: `Tajammul Ahmed` (the NAME, not email)

---

## Power BI Dataset Requirements

Your Power BI dataset MUST have a column containing salesperson names that EXACTLY match the MySQL `users.name` column.

### Required Column Examples:
- Column name: `Salesperson name` or `SalespersonName` or `Sales Person`
- Values must match MySQL exactly:
  - ✅ `Tajammul Ahmed` (matches MySQL)
  - ❌ `tajammul ahmed` (wrong - case mismatch)
  - ❌ `Tajamul Ahmed` (wrong - spelling mismatch)
  - ❌ `T. Ahmed` (wrong - abbreviation)

---

## Power BI RLS Configuration Steps

### Step 1: Open Power BI Desktop
1. Open your `.pbix` file
2. Go to the **Modeling** tab

### Step 2: Create RLS Roles
Click **Manage Roles** and create these 4 roles:

---

#### **Role 1: National**
- **Purpose**: Admin and Sales Head see all data
- **DAX Filter**: Leave blank (no filter = all data)

```dax
(No filter required)
```

---

#### **Role 2: KHI**
- **Purpose**: CMD-KHI sees only Karachi data
- **DAX Filter** (adjust column names to match your dataset):

```dax
[Location] = "KHI"
|| [OU_ID] IN (102, 103, 104, 105, 106)
|| [Region] = "Karachi"
```

---

#### **Role 3: LHR**
- **Purpose**: CMD-LHR sees only Lahore data
- **DAX Filter** (adjust column names to match your dataset):

```dax
[Location] = "LHR"
|| [OU_ID] IN (108, 109)
|| [Region] = "Lahore"
```

---

#### **Role 4: Salesperson** ⭐ (Most Important)
- **Purpose**: Salespeople see only their own data
- **DAX Filter** (adjust column name to match your dataset):

```dax
[Salesperson name] = USERPRINCIPALNAME()
```

**IMPORTANT**:
- Replace `[Salesperson name]` with YOUR actual column name
- Common column names: `Salesperson name`, `SalespersonName`, `Sales Person`, `Rep Name`, `Employee Name`
- The column MUST contain exact names from MySQL `users.name`

---

### Step 3: Test RLS Locally in Power BI Desktop

1. Go to **Modeling** tab → **View as Roles**
2. Select the **Salesperson** role
3. Check **Other user** and enter: `Tajammul Ahmed`
4. Click **OK**

**Expected Result**: Only data for Tajammul Ahmed should be visible in all report pages.

---

### Step 4: Publish to Power BI Service

1. Click **Publish** in Power BI Desktop
2. Select your workspace: `QG Sales Reports` (or your workspace name)
3. Click **Select**

---

### Step 5: Configure RLS in Power BI Service

1. Go to **Power BI Service** (app.powerbi.com)
2. Navigate to your workspace
3. Find your **Dataset** (not the report)
4. Click **...** (More options) → **Security**
5. You should see 4 roles: National, KHI, LHR, Salesperson

**DO NOT add users to these roles** - Laravel handles this dynamically via embed tokens.

---

## Verification & Testing

### Test 1: Admin User
1. Login to Laravel as Admin user
2. Navigate to BI Dashboard
3. **Expected Badge**: "National View" (blue)
4. **Expected Data**: All salespeople data visible

### Test 2: CMD-KHI User
1. Login to Laravel as CMD-KHI user
2. Navigate to BI Dashboard
3. **Expected Badge**: "KHI View" (green)
4. **Expected Data**: Only Karachi salespeople data

### Test 3: Tajammul Ahmed (Salesperson)
1. Login to Laravel as Tajammul Ahmed
2. Navigate to BI Dashboard
3. **Expected Badge**: "Personal View - Tajammul Ahmed" (orange)
4. **Expected Data**: Only Tajammul Ahmed's sales data
5. Check `storage/logs/laravel.log` for:
   ```
   Power BI RLS Configuration: username=Tajammul Ahmed, roles=['Salesperson']
   ```

---

## Troubleshooting

### Issue 1: Salesperson sees no data

**Possible Causes**:
- Name mismatch between MySQL and Power BI
- Check exact spelling and capitalization
- Check for extra spaces

**Solution**:
```sql
-- Run this in MySQL to get exact name:
SELECT name FROM users WHERE email = 'tajamul.ahmed@quadri-group.com';
-- Result should be: Tajammul Ahmed

-- Compare with Power BI column (use Power Query):
-- Check DISTINCT values in [Salesperson name] column
```

### Issue 2: Salesperson sees all data (RLS not working)

**Possible Causes**:
- RLS role not created in Power BI
- DAX filter syntax error
- Column name mismatch

**Solution**:
1. Re-check the DAX filter in Power BI Desktop
2. Test with "View as Roles" feature
3. Verify column name matches exactly: `[Salesperson name]`

### Issue 3: Token expired or authentication error

**Possible Causes**:
- Service principal doesn't have permissions
- Dataset not configured for embedding

**Solution**:
1. Verify service principal has access to workspace
2. Check dataset settings → Embed settings → Allow embedding

---

## Common Column Name Variations

If your Power BI column is named differently, update the DAX filter accordingly:

| If Column Name Is | Use This DAX Filter |
|-------------------|---------------------|
| `Salesperson name` | `[Salesperson name] = USERPRINCIPALNAME()` |
| `SalespersonName` | `[SalespersonName] = USERPRINCIPALNAME()` |
| `Sales Person` | `[Sales Person] = USERPRINCIPALNAME()` |
| `Rep Name` | `[Rep Name] = USERPRINCIPALNAME()` |
| `Employee Name` | `[Employee Name] = USERPRINCIPALNAME()` |
| `Full Name` | `[Full Name] = USERPRINCIPALNAME()` |

---

## Name Matching Reference

Ensure these names match EXACTLY between MySQL and Power BI:

| MySQL users.name | Power BI [Salesperson name] | Match? |
|------------------|----------------------------|--------|
| Tajammul Ahmed | Tajammul Ahmed | ✅ Yes |
| Tajammul Ahmed | TAJAMMUL AHMED | ❌ No (case) |
| Tajammul Ahmed | Tajamul Ahmed | ❌ No (spelling) |
| Tajammul Ahmed | Tajammul  Ahmed | ❌ No (extra space) |

---

## Additional Notes

1. **Case Sensitivity**: Power BI DAX filters are case-INSENSITIVE by default, but it's best practice to match exactly.

2. **Special Characters**: Avoid special characters in names. Stick to alphanumeric and spaces.

3. **Performance**: RLS filters are applied at query time. For large datasets, ensure proper indexing.

4. **Updates**: If you add new salespeople to MySQL, ensure their names are also in the Power BI dataset.

5. **Service Account**: The account `qgbi@quadrigroupcom.onmicrosoft.com` must have:
   - Member or Admin role in the workspace
   - Access to the gateway (if using on-premises data)
   - Dataset read permissions

---

## Support

If you encounter issues:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check Power BI Service Activity Log
3. Test RLS locally in Power BI Desktop first
4. Verify name matching between MySQL and Power BI dataset

---

**Last Updated**: December 2024
**Laravel Version**: 11.9
**Power BI Embedded Version**: API v1.0
