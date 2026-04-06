<!DOCTYPE html>
<html>
<head>
    <title>Check Borrower Debug Test</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .test-section { background: white; padding: 20px; margin: 15px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .test-section h3 { color: #333; margin-top: 0; }
        input, button { padding: 8px 12px; margin: 5px; font-size: 14px; }
        button { background: #0066cc; color: white; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0052a3; }
        .output { background: #f0f0f0; padding: 10px; margin: 10px 0; border-radius: 4px; font-family: monospace; white-space: pre-wrap; word-break: break-all; min-height: 30px; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        .info { background: #d1ecf1; color: #0c5460; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        table, th, td { border: 1px solid #ddd; }
        th, td { padding: 10px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>

<h1>Check Borrower Feature - Debug Test Page</h1>

<div class="test-section">
    <h3>✓ Available Borrowers</h3>
    <table>
        <tr>
            <th>BorrowerId</th>
            <th>ID Number</th>
            <th>Full Name</th>
        </tr>
        <tr>
            <td>1</td>
            <td>2024-0001</td>
            <td>John Doe</td>
        </tr>
        <tr>
            <td>2</td>
            <td>2024-0002</td>
            <td>Jane Smith</td>
        </tr>
        <tr>
            <td>3</td>
            <td>2024-0003</td>
            <td>Mark Anthony Sy</td>
        </tr>
    </table>
</div>

<div class="test-section">
    <h3>Test 1: Backend API Endpoint</h3>
    <p>Test if the check_borrower.php endpoint works correctly</p>
    <input type="text" id="testBorrowerId" placeholder="Enter Borrower ID (e.g., 2024-0001)" value="2024-0001">
    <button onclick="testBackendAPI()">Test API Call</button>
    <div id="test1Output" class="output"></div>
</div>

<div class="test-section">
    <h3>Test 2: JavaScript Highlighting Function</h3>
    <p>Test if the highlighting function works on dummy table rows</p>
    <button onclick="testHighlighting()">Create Test Table & Highlight</button>
    <div id="testTableContainer"></div>
    <div id="test2Output" class="output"></div>
</div>

<div class="test-section">
    <h3>Test 3: Full Integration Test</h3>
    <p>Simulate the complete Check button flow</p>
    <input type="text" id="integrationBorrowerId" placeholder="Enter Borrower ID" value="2024-0003">
    <button onclick="testFullFlow()">Test Full Check Flow</button>
    <div id="test3Output" class="output"></div>
</div>

<script>
    function log(elementId, message, type = 'info') {
        const output = document.getElementById(elementId);
        const timestamp = new Date().toLocaleTimeString();
        output.innerHTML += `<div class="${type}">[${timestamp}] ${message}</div>`;
        output.scrollTop = output.scrollHeight;
    }

    function clearLog(elementId) {
        document.getElementById(elementId).innerHTML = '';
    }

    // Test 1: Backend API
    async function testBackendAPI() {
        clearLog('test1Output');
        const borrowerId = document.getElementById('testBorrowerId').value.trim();
        
        if (!borrowerId) {
            log('test1Output', 'ERROR: Please enter a borrower ID', 'error');
            return;
        }

        log('test1Output', `Testing API with ID: ${borrowerId}`, 'info');
        log('test1Output', `URL: /jhcsc_seis/modules/transactions/check_borrower.php`, 'info');

        try {
            const response = await fetch('/jhcsc_seis/modules/transactions/check_borrower.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_number: borrowerId })
            });

            log('test1Output', `Response Status: ${response.status}`, 'info');
            const data = await response.json();
            
            if (data.success) {
                log('test1Output', `✓ SUCCESS - Borrower Found!`, 'success');
                log('test1Output', `Borrower ID: ${data.borrower.borrower_id}`, 'success');
                log('test1Output', `Full Name: ${data.borrower.full_name}`, 'success');
                log('test1Output', `Department: ${data.borrower.department}`, 'success');
                log('test1Output', `Contact: ${data.borrower.contact_no}`, 'success');
                log('test1Output', `Page: ${data.page}`, 'success');
            } else {
                log('test1Output', `✗ FAILED - ${data.message}`, 'error');
            }
        } catch (error) {
            log('test1Output', `✗ FETCH ERROR: ${error.message}`, 'error');
        }
    }

    // Test 2: Highlighting
    function testHighlighting() {
        clearLog('test2Output');
        const container = document.getElementById('testTableContainer');
        
        log('test2Output', 'Creating test table with 5 rows...', 'info');
        
        container.innerHTML = `
            <table>
                <tr>
                    <th>Borrower ID</th>
                    <th>Name</th>
                    <th>Department</th>
                </tr>
                <tr class="main-row" data-borrowerId="1" style="background: white;">
                    <td>1</td>
                    <td>John Doe</td>
                    <td>IT</td>
                </tr>
                <tr class="main-row" data-borrowerId="2" style="background: #f9f9f9;">
                    <td>2</td>
                    <td>Jane Smith</td>
                    <td>HR</td>
                </tr>
                <tr class="main-row" data-borrowerId="3" style="background: white;">
                    <td>3</td>
                    <td>Mark Anthony Sy</td>
                    <td>BSIT</td>
                </tr>
                <tr class="main-row" data-borrowerId="4" style="background: #f9f9f9;">
                    <td>4</td>
                    <td>Maria Clara Reyes</td>
                    <td>Engineering</td>
                </tr>
                <tr class="main-row" data-borrowerId="5" style="background: white;">
                    <td>5</td>
                    <td>Robert Lim</td>
                    <td>Finance</td>
                </tr>
            </table>
        `;

        log('test2Output', 'Table created. Now testing highlighting on borrower ID 3...', 'info');
        
        setTimeout(() => {
            const rows = container.querySelectorAll('tr.main-row');
            log('test2Output', `✓ Found ${rows.length} rows with class "main-row"`, 'success');
            
            let found = false;
            rows.forEach((row, index) => {
                const rowId = row.getAttribute('data-borrowerId');
                log('test2Output', `Row ${index}: data-borrowerId="${rowId}"`, 'info');
                
                if (rowId === '3') {
                    log('test2Output', `✓ Match found on row ${index}! Highlighting...`, 'success');
                    row.style.backgroundColor = '#fef08a';
                    row.style.boxShadow = 'inset 0 0 0 2px #f59e0b';
                    found = true;
                }
            });

            if (!found) {
                log('test2Output', '✗ No match found - check data-borrowerId attribute', 'error');
            }
        }, 500);
    }

    // Test 3: Full Flow
    async function testFullFlow() {
        clearLog('test3Output');
        const borrowerId = document.getElementById('integrationBorrowerId').value.trim();
        
        if (!borrowerId) {
            log('test3Output', 'ERROR: Please enter a borrower ID', 'error');
            return;
        }

        log('test3Output', `Starting full flow test with ID: ${borrowerId}`, 'info');

        // Step 1: Call backend
        log('test3Output', 'Step 1: Calling backend API...', 'info');
        try {
            const response = await fetch('/jhcsc_seis/modules/transactions/check_borrower.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_number: borrowerId })
            });

            const data = await response.json();
            
            if (data.success) {
                log('test3Output', `✓ Backend returned borrower: ${data.borrower.full_name}`, 'success');
                log('test3Output', `Borrower ID from backend: ${data.borrower.borrower_id}`, 'info');
                
                // Simulate form population
                log('test3Output', `Step 2: Would populate form with:`, 'info');
                log('test3Output', `  - Full Name: ${data.borrower.full_name}`, 'info');
                log('test3Output', `  - Department: ${data.borrower.department}`, 'info');
                log('test3Output', `  - Contact: ${data.borrower.contact_no}`, 'info');
                
                // Test highlighting
                log('test3Output', `Step 3: Testing highlight with borrower_id ${data.borrower.borrower_id}...`, 'info');
                log('test3Output', `Note: To see actual highlighting, run this on the REAL borrow.php page`, 'info');
                
            } else {
                log('test3Output', `✗ Backend error: ${data.message}`, 'error');
            }
        } catch (error) {
            log('test3Output', `✗ Error: ${error.message}`, 'error');
        }
    }
</script>

</body>
</html>
