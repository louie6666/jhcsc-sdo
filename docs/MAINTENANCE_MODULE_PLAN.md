# Maintenance Module (maintenance_list.php) - Development Plan

## 1. DATABASE STRUCTURE ANALYSIS

### Source Table: `Maintenance`
```
├── maintenance_id (INT) - Primary Key
├── equipment_id (INT) - Foreign Key to Equipment
├── item_record_id (INT NULL) - Foreign Key to Transaction_Items (for borrowed items)
├── date_reported (TIMESTAMP) - When damage was reported
├── issue_description (TEXT) - Description of the damage
└── repair_status (ENUM) - 'Pending', 'In-Repair', 'Fixed', 'Scrapped'
```

### Related Table: `Equipment`
```
├── equipment_id
├── name - Equipment name
├── storage_location
├── damaged_qty
└── available_qty
```

---

## 2. TABLE DISPLAY STRUCTURE

### Table Columns (7 columns total)
| Column | Width | Source | Display Format |
|--------|-------|--------|-----------------|
| Maintenance ID | 10% | maintenance_id | Formatted ID (e.g., M-2024-001) |
| Equipment Name | 20% | Equipment.name | Bold, with storage location below in muted text |
| Issue Description | 28% | issue_description | Text truncated, tooltip on hover |
| Date Reported | 12% | date_reported | n-j-y format (e.g., 4-7-26) |
| Repair Status | 12% | repair_status | Badge/Pill with color coding: |
| | | | • "Pending" = Orange (#f59e0b) |
| | | | • "In-Repair" = Blue (#3b82f6) |
| | | | • "Fixed" = Green (#10b981) |
| | | | • "Scrapped" = Gray (#64748b) |
| Actions | 8% | N/A | Single button only |

---

## 3. UI STYLING SPECIFICATIONS

### Container & Table Wrapper
- **Border Radius**: 8px (exact match to borrow table)
- **Shadow**: 0 1px 3px rgba(0,0,0,0.05) - subtle, professional
- **Background**: #ffffff (white card)
- **Wrapper Background**: #ecefec (light gray like borrow module)

### Table Header (th)
- **Background Color**: #8faadc (same blue as borrow table)
- **Text Color**: #ecefec (light)
- **Font Size**: 13px
- **Font Weight**: 400 (normal)
- **Text Transform**: UPPERCASE
- **Padding**: 10px 20px
- **Letter Spacing**: minimal

### Table Body (td)
- **Padding**: 10px 20px
- **Font Size**: 14px
- **Color**: #000000
- **Border Bottom**: 1px solid rgba(0,0,0,0.05)
- **Vertical Align**: middle

### Row Alternation
- **Odd Rows**: #ffffff
- **Even Rows**: #f9fafb

### Status Badge Styling
- **Padding**: 4px 8px
- **Border Radius**: 4px
- **Font Size**: 12px
- **Font Weight**: 600
- **Display**: inline-block

---

## 4. ACTION BUTTON SPECIFICATIONS

### Button Configuration
```
Type: Single Button Only
Label: "Fix"
Size: Small (6px padding, 10px horizontal)
Icon: material-symbols-outlined "build_circle" or "done_all"
Icon Size: 8px (matching updated borrow table)
```

### Button Appearance
- **Background**: White
- **Border**: 1px solid #e2e8f0
- **Border Radius**: 4px
- **Text Color**: #0c1f3f
- **Hover State**: 
  - Background: #8faadc
  - Text Color: white
  - Transition: all 0.2s

### Button Layout
- **Flex-based**: gap: 4px between button and icon
- **Alignment**: inline-flex, center-aligned

---

## 5. FUNCTIONAL BEHAVIOR

### "Fix" Button Workflow

#### Step 1: User Interaction
User clicks the "Fix" button on a maintenance record with repair_status = "Pending" or "In-Repair"

#### Step 2: Confirmation Dialog
System displays JavaScript confirm() with message:
```
"Mark equipment as FULLY FIXED? 
This will update repair_status to 'Fixed' and 
move the item back to available inventory."
```

#### Step 3: Backend Processing (POST Request)
- **Endpoint**: maintenance_list.php?action=mark_fixed
- **Payload**: 
  - maintenance_id
  - equipment_id
- **Database Updates**:
  1. Update `Maintenance.repair_status` to 'Fixed'
  2. Update `Equipment.damaged_qty` - 1
  3. Update `Equipment.available_qty` + 1

#### Step 4: Response Echo
On successful completion, show inline alert/toast:
```
✓ "Equipment [Name] is now FULLY FIXED and ready to use!"
```

#### Step 5: UI Update
- Button disabled temporarily (shows loading spinner - 8px hourglass icon)
- Show success icon (8px check_circle in green)
- Row fades or updates immediately
- Page auto-refreshes after 1 second

---

## 6. PAGE INITIALIZATION & DATA LOADING

### Header Section (Above Table)
```
Container:
├── Text Left: "Tracking [X] items pending repair"
└── Button Right: None (no "new maintenance" button - items auto-added)
```

### Data Query Logic
Fetch all maintenance records with:
- **Filter 1**: repair_status IN ('Pending', 'In-Repair')
- **Filter 2**: Sort by date_reported DESC (newest first)
- **Pagination**: 14 items per page (same as borrow module)
- **Join**: Equipment table to get name, storage_location, qty info

### Pagination
Same pattern as borrow.php:
- Previous/Next buttons
- Page numbers (centered)
- Active page highlighted in dark blue (#0c1f3f)

---

## 7. EDGE CASES & VALIDATION

### "Fix" Button Disabled When:
- repair_status = 'Fixed' (already marked as fixed)
- repair_status = 'Scrapped' (permanently broken, no action button)
- equipment_id has damaged_qty = 0 (data consistency check)

### Error Handling
- **No items pending**: Show centered message "No pending repairs."
- **Database error on update**: "Failed to update repair status. Try again."
- **Stock update fails**: "Equipment updated but inventory sync failed. Contact admin."

---

## 8. REUSABLE CSS CLASSES (From Borrow Table)

```css
.maint-container          /* Main wrapper, padding 40px */
.maint-header-row         /* Flex header with stats */
.maint-header-text        /* Stats text */
.maint-divider            /* <hr> separator */
.maint-table-wrapper      /* Table card with shadow */
.maint-table              /* Table element */
.maint-table th           /* Header styling */
.maint-table td           /* Cell styling */
.maint-table tbody tr.main-row  /* Row hover state */
.actions-cell             /* Flex container for buttons */
.btn-action               /* Action button styling */
.status-badge            /* Status pill/badge */
.status-pending          /* Orange badge */
.status-in-repair        /* Blue badge */
.status-fixed            /* Green badge */
.status-scrapped         /* Gray badge */
```

---

## 9. FILE STRUCTURE & CODE ORGANIZATION

### PHP Sections (Architecture from borrow.php)
```
1. SESSION & INITIALIZATION
   ├── session_start()
   └── include connection.php

2. REQUEST DETECTION
   ├── Check if POST (API) or GET (page load)
   └── Route to handleApiRequest() if API

3. PAGE DATA LOADING
   ├── loadPageData() - pagination, sorting
   └── getMaintenanceRecords() - DB query with joins

4. HTML RENDERING
   ├── renderMaintenancePage() - HTML template
   └── Inline CSS & JS

5. API HANDLERS
   ├── handleMarkFixed() - Update repair_status & inventory
   └── Error responses

6. HELPER FUNCTIONS
   ├── getMaintenanceStats()
   ├── getMaintenanceItems()
   └── updateEquipmentStatus()
```

---

## 10. IMMEDIATE NEXT STEPS (Implementation Order)

1. **Create basic HTML template** → minimal working table structure
2. **Write database query** → fetch maintenance + equipment joins
3. **Build CSS styling** → reuse borrow.php classes/variables
4. **Add "Fix" button logic** → validation + confirmation
5. **Implement API handler** → POST endpoint for status update
6. **Add pagination** → copy logic from borrow.php
7. **Test edge cases** → invalid IDs, inventory sync, permissions
8. **Add status badges** → color-coded repair status display

---

## 11. KEY METRICS & DISPLAYS

### Page Statistics (Header)
- **Total Pending**: COUNT WHERE repair_status IN ('Pending', 'In-Repair')
- **Total Items**: COUNT of all records
- Display: "Tracking **X** items pending repair out of **Y** total"

### Success Indicators
- ✓ Green checkmark when repair_status updates to 'Fixed'
- ✓ Damaged qty decreases
- ✓ Available qty increases automatically

---

## DESIGN PHILOSOPHY

**"Mirror the Borrow Module Perfectly"**
- Same table structure, column alignment, spacing
- Same color scheme, typography, shadows
- Same pagination, error handling, UX patterns
- Re-use existing CSS variables and classes
- Same responsive behavior (if applicable)

**Single Responsibility Per Button**
- Only action: Mark as Fixed
- One click = one database transaction
- Clear success/failure feedback
- No unnecessary modals or dialogs

