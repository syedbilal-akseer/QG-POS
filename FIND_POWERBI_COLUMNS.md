# How to Find Table and Column Names in Power BI

## Problem
The filters are being "applied successfully" but not actually filtering the data because we're using incorrect table/column names.

## Current Filter Configuration

**File**: `resources/views/admin/bi-dashboard.blade.php`

**For Salespeople** (Line 290-292):
```javascript
table: "Sales Person Hierarchy",  // <-- Need to verify this
column: "Name"                     // <-- Need to verify this
```

**For Location** (Line 308-309):
```javascript
table: "Sales",     // <-- Need to verify this
column: "OU_ID"     // <-- Need to verify this
```

---

## How to Find the Correct Names

### Option 1: Using Power BI Desktop (Recommended)

1. **Open your report in Power BI Desktop**
2. Go to **Model View** (left sidebar icon)
3. You'll see all your tables listed on the right
4. Click on a table to see all its columns

**Look for**:
- Table containing salesperson names
- Column containing salesperson names (e.g., "Tajammul Ahmed")
- Table containing location/OU data
- Column containing OU IDs (102, 103, etc.)

### Option 2: Using Power BI Service

1. **Go to Power BI Service** (app.powerbi.com)
2. Open your dataset (NOT the report)
3. Click **Edit** in the toolbar
4. You'll see the data model with table and column names

### Option 3: Check a Visual

1. **In Power BI Desktop**, click on any visual showing salesperson data
2. Look at the **Fields** pane on the right
3. You'll see the table and column name like: `TableName[ColumnName]`

Example:
```
Sales Person Hierarchy[Name]        <-- Table: "Sales Person Hierarchy", Column: "Name"
Salesperson[Salesperson Name]       <-- Table: "Salesperson", Column: "Salesperson Name"
Sales[Rep Name]                     <-- Table: "Sales", Column: "Rep Name"
```

---

## Common Naming Patterns

### Salesperson Tables
- `Sales Person Hierarchy`
- `Salesperson`
- `Sales Person`
- `Employee`
- `Users`
- `Dim_Salesperson`

### Salesperson Columns
- `Name`
- `Salesperson name`
- `Sales Person`
- `Full Name`
- `Employee Name`
- `Rep Name`

### Location/OU Tables
- `Sales`
- `Orders`
- `Transactions`
- `Fact_Sales`

### Location/OU Columns
- `OU_ID`
- `Organization Unit`
- `Location`
- `Region`
- `Office`

---

## How to Update the Code

Once you find the correct table and column names:

### For Salesperson Filtering

**Edit**: `/mnt/d/pos/resources/views/admin/bi-dashboard.blade.php`

**Find** (around line 290):
```javascript
filterToApply = {
    $schema: "http://powerbi.com/product/schema#basic",
    target: {
        table: "Sales Person Hierarchy",  // <-- CHANGE THIS
        column: "Name"                     // <-- CHANGE THIS
    },
```

**Replace with YOUR table and column names**:
```javascript
filterToApply = {
    $schema: "http://powerbi.com/product/schema#basic",
    target: {
        table: "YourActualTableName",     // e.g., "Salesperson"
        column: "YourActualColumnName"    // e.g., "Salesperson Name"
    },
```

### For Location/OU Filtering

**Find** (around line 308):
```javascript
filterToApply = {
    $schema: "http://powerbi.com/product/schema#basic",
    target: {
        table: "Sales",     // <-- CHANGE THIS
        column: "OU_ID"     // <-- CHANGE THIS
    },
```

**Replace with YOUR table and column names**:
```javascript
filterToApply = {
    $schema: "http://powerbi.com/product/schema#basic",
    target: {
        table: "YourActualTableName",   // e.g., "Orders"
        column: "YourActualColumnName"  // e.g., "Organization Unit"
    },
```

---

## Testing After Update

1. **Save the blade file**
2. **Clear browser cache**: `Ctrl + Shift + R`
3. **Login as Tajammul Ahmed**
4. **Open browser console** (F12)
5. **Look for these logs**:

### Expected Success:
```
Applying user filters: {type: 'salesperson', salespersonName: 'Tajammul Ahmed'}
Attempting filter for salesperson: Tajammul Ahmed
Existing filters: []
✅ Filter applied successfully to active page
Current filters after apply: [{...}]  <-- Should show 1 filter
```

### If Still Failing:
```
❌ Filter application failed: Error: ...
Trying alternative approach with report-level filter...
```

**The error message will tell you what's wrong** (e.g., "Table not found", "Column not found")

---

## Quick Test in Browser Console

You can test table/column names directly in the browser:

1. **Open BI Dashboard**
2. **Open Console** (F12)
3. **Paste this code**:

```javascript
// Test a filter manually
globalReport.getPages().then(pages => {
    const activePage = pages.find(p => p.isActive);
    const testFilter = {
        $schema: "http://powerbi.com/product/schema#basic",
        target: {
            table: "YOUR_TABLE_NAME",    // <-- Try different table names
            column: "YOUR_COLUMN_NAME"   // <-- Try different column names
        },
        operator: "In",
        values: ["Tajammul Ahmed"],
        filterType: 1
    };

    activePage.setFilters([testFilter])
        .then(() => console.log('✅ Filter worked!'))
        .catch(err => console.error('❌ Filter failed:', err.message));
});
```

Try different combinations until you find the one that works!

---

## Example from Your Console Logs

Your console showed:
```
Filter applied successfully: Name
```

This means **column "Name" exists**, but we need to find the **correct table** that contains it.

---

## Next Steps

1. **Find the correct table and column names** using one of the methods above
2. **Update the blade file** with the correct names
3. **Test again** and check console logs
4. **Let me know** the correct table/column names so I can update the code permanently

---

## Need Help?

Provide me with:
1. Screenshot of Power BI Desktop Model View showing your tables
2. OR list of table names from your Power BI dataset
3. OR the console error message you're seeing

I'll help you identify the correct names! 🎯
