# Power BI RLS Fix Guide

## Problem Summary

**Issue**: All users see everyone's data in the Power BI dashboard, despite RLS being configured.

**Root Cause**: The Power BI RLS configuration in Power BI Desktop/Service needs to be verified and corrected.

**Laravel Status**: ✅ Laravel code is working correctly and sending proper RLS configuration to Power BI.

---

## Step 1: Access the Diagnostic Page

1. Login to the application
2. Navigate to: `/admin/bi-dashboard/diagnostic`
3. This page will show you:
   - Your user information
   - The effective identity being sent to Power BI
   - The RLS roles being applied
   - Expected behavior for your role
   - Recent Power BI logs

---

## Step 2: Verify Power BI Desktop Configuration

### 2.1 Open Power BI Desktop

1. Open the `.pbix` file for your report
2. Make sure you're working on the correct report that's published to Power BI Service

### 2.2 Create/Verify the "National" Role

1. Click **Modeling** tab → **Manage roles**
2. Check if a role named **"National"** exists (case-sensitive!)
3. If it doesn't exist:
   - Click **New**
   - Name it exactly: `National`

### 2.3 Configure the DAX Filter

1. In Manage roles dialog, select the **National** role
2. In the table list, find and select **DIM_SALESREP** table
3. In the filter DAX box, enter EXACTLY:
   ```dax
   [Name] = USERPRINCIPALNAME()
   ```

**CRITICAL**:
- The table name must be exact (case-sensitive)
- The column name must be exact (case-sensitive)
- If your table or column has a different name, adjust accordingly

### 2.4 Verify Data in DIM_SALESREP Table

1. Click **Data** view (left sidebar icon)
2. Click on **DIM_SALESREP** table
3. Look at the **Name** column
4. **IMPORTANT**: Copy a few names from this column exactly as they appear

Example data:
```
Name
-----------------
Tajammul Ahmed
Muhammad Ali
Sarah Khan
```

**Check for issues**:
- ❌ Trailing spaces: "Tajammul Ahmed " (space at end)
- ❌ Case differences: "TAJAMMUL AHMED" vs "Tajammul Ahmed"
- ❌ Different format: "Ahmed, Tajammul" vs "Tajammul Ahmed"

The effective identity from Laravel MUST match these values EXACTLY.

### 2.5 Test RLS in Power BI Desktop

1. Click **Modeling** tab → **View as**
2. Check the checkbox for **"National"** role
3. In the **"Other user"** text box below, enter: `Tajammul Ahmed`
   (Use an actual name from your DIM_SALESREP table)
4. Click **OK**

**Expected Result**:
- You should now see ONLY data for "Tajammul Ahmed"
- All visuals should be filtered
- Check multiple pages to confirm

**If you see all data**:
- ❌ The DAX filter is incorrect
- ❌ The table/column name is wrong
- ❌ The data doesn't match the test name

**Fix before proceeding to next step!**

---

## Step 3: Publish to Power BI Service

1. In Power BI Desktop, click **File** → **Publish** → **Publish to Power BI**
2. Select your workspace (the one configured in Laravel `.env`)
3. Wait for publishing to complete
4. Click **Open [report name] in Power BI** (or close the dialog)

---

## Step 4: Verify RLS in Power BI Service

### 4.1 Navigate to Dataset Security

1. Go to [https://app.powerbi.com](https://app.powerbi.com)
2. Navigate to your workspace
3. Find the **Dataset** (NOT the report - it's a different icon)
4. Click **...** (more options) next to the dataset
5. Click **Security**

### 4.2 Verify Role Configuration

You should see:
- **National** role listed
- The DAX rule displayed: `[Name] = USERPRINCIPALNAME()`
- No members listed (we're using effective identity, not static members)

### 4.3 Test RLS in Power BI Service

1. Still in the Security page, find the **"National"** role
2. Click **"Test as role"** button
3. In the username field, enter: `Tajammul Ahmed` (use an actual name from your data)
4. Click **Test**

**Expected Result**:
- The report should reload
- You should see ONLY data for "Tajammul Ahmed"
- Check all three pages:
  - AR Summary
  - Target vs Achieved
  - Sales 360

**If it still shows all data**:
- The RLS is not configured correctly in the dataset
- Go back to Step 2 and verify everything again
- Make sure you published after making changes

---

## Step 5: Test in Laravel Application

1. Clear Power BI cache: Visit `/admin/bi-dashboard/clear-cache`
2. Login as a salesperson user (e.g., Tajammul Ahmed)
3. Navigate to: `/admin/bi-dashboard`
4. **Check the console logs** (F12 → Console tab):
   - Should show: `🔐 RLS Configuration`
   - Should show effective identity
   - Should show RLS roles

**Expected Result**:
- Salesperson should see ONLY their data
- Admin should see ALL data

**If still not working**:
- Check diagnostic page: `/admin/bi-dashboard/diagnostic`
- Check Laravel logs: `tail -f storage/logs/laravel.log | grep "Power BI"`
- Verify the effective identity matches the name in DIM_SALESREP table

---

## Step 6: Common Issues and Solutions

### Issue 1: "FailedToLoadModel" Error

**Symptom**: Dashboard shows error about failed to load model

**Cause**:
- Power BI dataset doesn't have RLS configured
- The "National" role doesn't exist
- Dataset wasn't published after adding RLS

**Solution**:
1. Go back to Step 2
2. Verify role exists in Power BI Desktop
3. Publish again to Power BI Service
4. Wait 2-3 minutes for changes to propagate

### Issue 2: All Users See All Data

**Symptom**: RLS seems to work in Power BI Desktop but not in Laravel embed

**Cause**:
- Effective identity format doesn't match data
- Dataset ID is incorrect

**Solution**:
1. Check diagnostic page - verify effective identity
2. Compare with actual data in DIM_SALESREP table
3. Check for case sensitivity, spaces, formatting
4. Check Laravel logs for the dataset ID being used

### Issue 3: Admin Sees Filtered Data (Should See All)

**Symptom**: Admin user is filtered to one salesperson

**Cause**: Admin's effective identity matches a name in DIM_SALESREP

**Current Laravel Configuration**:
- Admin effective identity: `qgbi@quadrigroupcom.onmicrosoft.com`
- This should NOT match any name in DIM_SALESREP
- So RLS filter won't apply = admin sees all data

**Solution**:
1. Verify the service account email doesn't appear in DIM_SALESREP[Name]
2. If it does, change it in `BIDashboardController.php` line 93

### Issue 4: Data Format Mismatch

**Symptom**: Salesperson name in Laravel doesn't match Power BI data

**Example**:
- Laravel sends: "Tajammul Ahmed"
- Power BI has: "TAJAMMUL AHMED" (all caps)
- RLS fails because strings don't match

**Solution Option 1** (Recommended): Fix Power BI data
```dax
// In Power Query Editor
= Table.TransformColumns(#"Previous Step",{{"Name", Text.Proper}})
```

**Solution Option 2**: Use case-insensitive DAX
```dax
UPPER([Name]) = UPPER(USERPRINCIPALNAME())
```

**Solution Option 3**: Fix Laravel to send uppercase
```php
// In BIDashboardController.php line 89
return strtoupper($user->name);
```

---

## Step 7: Advanced Troubleshooting

### Enable Detailed Logging

The Laravel code now has detailed logging. Check logs:

```bash
# Watch logs in real-time
tail -f storage/logs/laravel.log | grep "Power BI"

# View recent Power BI logs
grep "Power BI" storage/logs/laravel.log | tail -50
```

You should see:
```
Power BI: RLS Token Request
  username: Tajammul Ahmed
  roles: ["National"]
  dataset_id: xxx-xxx-xxx

Power BI: Embed Token Generated Successfully
  username: Tajammul Ahmed
  has_identity: true
```

### Check Dataset ID

The dataset ID must be correct for RLS to work:

1. Go to Power BI Service
2. Open your workspace
3. Click on the dataset
4. Look at the URL - it contains the dataset ID
5. Compare with the ID in Laravel logs

---

## Step 8: Verify Final Configuration

### Checklist:

**Power BI Desktop:**
- [ ] Role "National" exists
- [ ] DAX filter: `[Name] = USERPRINCIPALNAME()`
- [ ] Correct table name: DIM_SALESREP
- [ ] Correct column name: Name
- [ ] Tested with "View as" - works correctly
- [ ] Published to Power BI Service

**Power BI Service:**
- [ ] Dataset security shows "National" role
- [ ] DAX rule is visible
- [ ] Tested "Test as role" - works correctly
- [ ] Report ID matches `.env` configuration
- [ ] Workspace ID matches `.env` configuration

**Laravel:**
- [ ] Diagnostic page shows correct configuration
- [ ] Effective identity format matches Power BI data
- [ ] Logs show successful token generation
- [ ] Cache cleared after configuration changes

**Data Quality:**
- [ ] Names in DIM_SALESREP match Laravel user names
- [ ] No trailing spaces in data
- [ ] Case matches (or using case-insensitive DAX)
- [ ] Format matches (full name vs email vs ID)

---

## Expected Behavior After Fix

### For Salespeople (role: user, hod, line-manager):
- ✅ See ONLY their own data
- ✅ Name matches DIM_SALESREP[Name]
- ✅ Effective identity = their name
- ✅ All three report pages filtered

### For Admins (role: admin) and Sales Head (role: sales-head):
- ✅ See ALL salesperson data
- ✅ Effective identity = service account (doesn't match any name)
- ✅ RLS doesn't restrict data
- ✅ All three report pages show all data

### For Location Managers (role: cmd-khi, cmd-lhr):
- ✅ See data for their location only
- ✅ Filtered by OU ID in Power BI
- ✅ KHI: OU IDs 102-106
- ✅ LHR: OU IDs 108-109

---

## Quick Reference: Key Files Modified

1. **app/Services/PowerBIService.php**
   - Enhanced logging for RLS token generation
   - Validates dataset ID exists

2. **app/Http/Controllers/Admin/BIDashboardController.php**
   - Added `diagnostic()` method for RLS debugging
   - Added `clearCache()` method
   - Enhanced effective identity logic

3. **resources/views/admin/bi-dashboard.blade.php**
   - Added console logging for RLS configuration
   - Shows effective identity and roles

4. **resources/views/admin/bi-dashboard-diagnostic.blade.php**
   - NEW: Comprehensive diagnostic page
   - Shows user info, RLS config, expected behavior
   - Displays recent logs

5. **routes/web.php**
   - Added `/admin/bi-dashboard/diagnostic` route
   - Added `/admin/bi-dashboard/clear-cache` route

---

## Testing the Fix

### Test Case 1: Salesperson User

1. Login as salesperson (e.g., Tajammul Ahmed)
2. Go to `/admin/bi-dashboard/diagnostic`
3. Verify:
   - Effective Identity: "Tajammul Ahmed"
   - RLS Roles: ["National"]
   - Expected Behavior: Should see ONLY data for Tajammul Ahmed
4. Click "View Power BI Dashboard"
5. Check all three pages - should show only their data

### Test Case 2: Admin User

1. Login as admin
2. Go to `/admin/bi-dashboard/diagnostic`
3. Verify:
   - Effective Identity: "qgbi@quadrigroupcom.onmicrosoft.com"
   - RLS Roles: ["National"]
   - Expected Behavior: Should see ALL data
4. Click "View Power BI Dashboard"
5. Check all three pages - should show all salespeople data

### Test Case 3: Multiple Salespeople

1. Login as Salesperson A
2. Note which data you see
3. Logout and login as Salesperson B
4. Data should change to show only Salesperson B's data
5. Verify no overlap between the two views

---

## Support Resources

- **Diagnostic Page**: `/admin/bi-dashboard/diagnostic`
- **Clear Cache**: `/admin/bi-dashboard/clear-cache`
- **Laravel Logs**: `storage/logs/laravel.log`
- **Power BI Service**: [https://app.powerbi.com](https://app.powerbi.com)

---

## Summary

The Laravel application is correctly configured and sending proper RLS information to Power BI. The issue is in the Power BI configuration itself:

1. **Power BI Desktop**: Must have "National" role with correct DAX filter
2. **Power BI Data**: Names must match Laravel user names exactly
3. **Power BI Service**: Dataset must have RLS configured after publishing
4. **Testing**: Must test in Power BI Service before testing in Laravel

Follow this guide step by step, and RLS will work correctly!
