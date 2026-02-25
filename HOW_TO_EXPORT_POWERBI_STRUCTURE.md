# How to Export Power BI Report Structure

## What This Does

When you load the BI Dashboard, it will automatically:
1. Detect all pages in your Power BI report
2. List all visuals on each page
3. Show current filters (if any)
4. Save this information to 2 files:
   - **JSON file** - Full detailed structure
   - **TXT file** - Human-readable summary

## How to Use

### Step 1: Login and Open BI Dashboard

1. **Login to your Laravel app** as any user (Tajammul Ahmed, Admin, etc.)
2. **Navigate to BI Dashboard** from the sidebar
3. **Wait for the report to load**

### Step 2: Check Browser Console

After the report loads (wait ~1-2 seconds), open **Browser Console (F12)** and you'll see:

```
📊 Report has 3 pages
  Page "AR Summary" has 12 visuals
  Page "Target vs Acheived" has 8 visuals
  Page "Sales 360" has 15 visuals
📄 Full Report Structure: {timestamp: "...", pages: [...]}
✅ Report structure saved to: powerbi_structure_2024-12-01_143025.json
📥 Download at: http://your-url/admin/bi-dashboard/download-structure/powerbi_structure_2024-12-01_143025.json
```

### Step 3: Download the Files

You'll see an **alert popup** saying:
```
✅ Report structure saved!

File: powerbi_structure_2024-12-01_143025.json

Check browser console for details.
```

**Click OK**, then check console for download links.

### Step 4: Find the Files

**Option 1: Download via URL** (shown in console)
- JSON: `http://your-url/admin/bi-dashboard/download-structure/powerbi_structure_TIMESTAMP.json`
- TXT: `http://your-url/admin/bi-dashboard/download-structure/powerbi_structure_TIMESTAMP.txt`

**Option 2: Access from Server**
Files are saved to: `/mnt/d/pos/storage/app/powerbi/`

```bash
# List all saved structures
ls -lah /mnt/d/pos/storage/app/powerbi/

# View the latest text file
cat /mnt/d/pos/storage/app/powerbi/powerbi_structure_*.txt | tail -100

# Copy to your desktop
cp /mnt/d/pos/storage/app/powerbi/powerbi_structure_*.txt ~/Desktop/
```

---

## What the Files Contain

### TXT File (Human Readable)
```
POWER BI REPORT STRUCTURE
Generated: 2024-12-01 14:30:25
User: Tajammul Ahmed (tajamul.ahmed@quadri-group.com)
User Filter Type: salesperson
================================================================================

PAGE 1: AR Summary
  Name: 278e096ded9af02300d1
  Active: Yes
  Visuals: 12
  Page Filters:
    (none)

PAGE 2: Target vs Acheived
  Name: fcac9c10c7c6243ca015
  Active: No
  Visuals: 8
  Page Filters:
    - Table: Sales, Column: Salesperson name

PAGE 3: Sales 360
  Name: 90a836ec7301f06da67d
  Active: No
  Visuals: 15
```

### JSON File (Full Details)
```json
{
  "timestamp": "2024-12-01T14:30:25.123Z",
  "userFilter": {
    "type": "salesperson",
    "salespersonName": "Tajammul Ahmed",
    "ouIds": [],
    "location": null
  },
  "pages": [
    {
      "name": "278e096ded9af02300d1",
      "displayName": "AR Summary",
      "isActive": true,
      "visuals": [
        {
          "name": "abc123",
          "title": "Sales by Person",
          "type": "tableEx",
          "layout": {...}
        }
      ],
      "filters": []
    }
  ],
  "user": {
    "id": 1,
    "name": "Tajammul Ahmed",
    "email": "tajamul.ahmed@quadri-group.com",
    "role": "user"
  }
}
```

---

## What to Look For

### 1. Page Filters (Most Important!)
If you see existing filters in the **Page Filters** section, those show which **table and column names** are already being used in Power BI:

```
Page Filters:
  - Table: Sales Person Hierarchy, Column: Name
  - Table: Sales, Column: OU_ID
```

**This tells us**:
- Table: `Sales Person Hierarchy`
- Column: `Name`

### 2. Visual Information
The visuals list shows all charts/tables on each page. This helps understand the report structure.

### 3. Active Page
Shows which page was active when you loaded the dashboard.

---

## Sharing the File

### Send to Me:

**Option 1: Copy-Paste** (Quick)
```bash
cat /mnt/d/pos/storage/app/powerbi/powerbi_structure_*.txt
```
Copy the output and paste it in your message.

**Option 2: Download and Attach**
1. Download the file via the browser
2. Attach to email or message

---

## Troubleshooting

### No Alert Popup?
**Check browser console (F12)** for errors. You should see:
```
📊 Report has X pages
```

If you see errors, the report might not be fully loaded yet.

### "Error getting filters" in the file?
This is normal if the page has no filters. Not a problem.

### File not saving?
Check Laravel logs:
```bash
tail -f /mnt/d/pos/storage/logs/laravel.log
```

Look for:
```
Power BI structure saved
```

### Empty visuals array?
The report might be taking longer to load. Try:
1. Refresh the page
2. Wait 5 seconds
3. Check console again

---

## Next Steps

After you share the file with me, I will:

1. ✅ Identify the correct **table names**
2. ✅ Identify the correct **column names**
3. ✅ Update the filter code in `bi-dashboard.blade.php`
4. ✅ Test the filters work correctly

Then Tajammul Ahmed will see ONLY his data! 🎯
