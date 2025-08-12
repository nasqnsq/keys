<?php
// إعدادات الاتصال بقاعدة البيانات
$servername = "sql109.infinityfree.com";
$username = "if0_39687927"; // قم بتعديل هذا
$password = "cdBVcJf1lhi"; // قم بتعديل هذا
$dbname = "if0_39687927_almahmoud";

// إنشاء الاتصال
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// البدء بجلسة PHP للحفاظ على بيانات الفاتورة الحالية
session_start();

// تحديد الفاتورة الحالية في الجلسة أو إنشاء فاتورة جديدة
if (!isset($_SESSION['current_invoice_id'])) {
    $stmt = $conn->prepare("INSERT INTO invoices (invoice_number, customer_name) VALUES ('', '')");
    $stmt->execute();
    $_SESSION['current_invoice_id'] = $conn->lastInsertId();
}
$current_invoice_id = $_SESSION['current_invoice_id'];

// معالجة طلبات POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    // 1. إضافة/تعديل صنف مخزن
    if ($action == 'save_item') {
        $name = $_POST['newItemName'];
        $price = $_POST['newItemPrice'];
        $id = $_POST['editingItemId'] ?? null;

        if ($id) {
            $stmt = $conn->prepare("UPDATE items SET price = ?, name = ? WHERE id = ?");
            $stmt->execute([$price, $name, $id]);
        } else {
            $stmt = $conn->prepare("INSERT INTO items (name, price) VALUES (?, ?)");
            $stmt->execute([$name, $price]);
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // 2. حذف صنف مخزن
    if ($action == 'delete_item') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // 3. مسح كل الأصناف المخزنة
    if ($action == 'clear_items') {
        $conn->exec("TRUNCATE TABLE items");
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // 4. إضافة صنف للفاتورة
    if ($action == 'add_to_invoice') {
        $productName = $_POST['productName'];
        $quantity = $_POST['quantity'];
        $unit = $_POST['unit'];
        $price = $_POST['price'];
        $notes = $_POST['notes'];
        
        $subtotal = $quantity * $price;
        
        $stmt = $conn->prepare("INSERT INTO invoice_items (invoice_id, item_name, quantity, unit, price, notes) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$current_invoice_id, $productName, $quantity, $unit, $price, $notes]);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // 5. تحديث معلومات الفاتورة
    if ($action == 'update_invoice_info') {
        $invoiceNumber = $_POST['invoiceNumber'];
        $customerName = $_POST['customerName'];
        $paidAmount = $_POST['paidAmount'];

        $stmt = $conn->prepare("UPDATE invoices SET invoice_number = ?, customer_name = ?, paid_amount = ? WHERE id = ?");
        $stmt->execute([$invoiceNumber, $customerName, $paidAmount, $current_invoice_id]);
        
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // 6. حذف صنف من الفاتورة
    if ($action == 'delete_invoice_item') {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM invoice_items WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // 7. مسح الفاتورة بالكامل
    if ($action == 'clear_invoice') {
        $stmt = $conn->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$current_invoice_id]);
        
        $stmt = $conn->prepare("UPDATE invoices SET invoice_number = '', customer_name = '', paid_amount = 0 WHERE id = ?");
        $stmt->execute([$current_invoice_id]);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// -----------------------------------------------------------
// جلب البيانات من قاعدة البيانات لعرضها في الصفحة
// -----------------------------------------------------------

// جلب جميع الأصناف المخزنة
$items = $conn->query("SELECT * FROM items ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// جلب معلومات الفاتورة الحالية
$invoice_info = $conn->prepare("SELECT * FROM invoices WHERE id = ?");
$invoice_info->execute([$current_invoice_id]);
$current_invoice = $invoice_info->fetch(PDO::FETCH_ASSOC);

// جلب أصناف الفاتورة الحالية
$invoice_items = $conn->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id DESC");
$invoice_items->execute([$current_invoice_id]);
$invoice_items = $invoice_items->fetchAll(PDO::FETCH_ASSOC);

$total_amount = 0;
foreach ($invoice_items as $item) {
    $total_amount += $item['quantity'] * $item['price'];
}

$remaining_amount = $total_amount - ($current_invoice['paid_amount'] ?? 0);
?>
<!DOCTYPE html>
<html lang="ar">
<head>
    <title>المحمود للمواد الغذائية</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 5px;
            background-color: #f0f2f5;
            color: #333;
            direction: rtl;
            font-size: 10px;
            
            display: flex;
            flex-direction: column;
            height: 100vh;
            box-sizing: border-box;
        }

        .main-header {
            text-align: center;
            margin-bottom: 5px;
            flex-shrink: 0;
        }

        .top-container {
            display: flex;
            flex-direction: row-reverse;
            gap: 5px;
            margin-bottom: 5px;
            flex-shrink: 0;
            height: auto;
        }

        .bottom-container {
            display: flex;
            flex-direction: column;
            flex-grow: 1;
            height: auto;
        }

        .card {
            background-color: #fff;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 8px;
            flex-grow: 1;
            flex-basis: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .card-content {
            overflow-y: auto;
            flex-grow: 1;
        }

        h1 {
            font-size: 14px;
            margin: 5px 0;
            color: #2c3e50;
            text-align: center;
        }

        h2 {
            font-size: 12px;
            color: #2c3e50;
            margin: 0 0 5px 0;
            text-align: right;
        }

        h3 {
            font-size: 10px;
            margin: 5px 0;
            text-align: right;
        }

        p {
            text-align: right;
            font-size: 9px;
            margin: 2px 0;
        }

        label {
            display: block;
            margin-top: 2px;
            font-weight: bold;
            color: #555;
            text-align: right;
            font-size: 9px;
        }

        hr {
            margin: 3px 0;
            border: 0;
            border-top: 1px solid #eee;
        }

        input[type="text"], input[type="number"], select {
            width: 100%;
            padding: 3px;
            margin-top: 2px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
            text-align: right;
            font-size: 9px;
        }

        button {
            width: 100%;
            padding: 6px;
            margin-top: 8px;
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 10px;
            transition: background-color 0.3s;
            box-sizing: border-box;
        }

        button:hover {
            background-color: #2980b9;
        }

        .button-group {
            display: flex;
            justify-content: space-between;
            gap: 3px;
        }

        .button-group button {
            width: 100%;
            margin-top: 5px;
            padding: 4px;
            font-size: 9px;
        }

        #addItemBtn { background-color: #27ae60; }
        #addItemBtn:hover { background-color: #219d54; }
        #clearItemsButton { background-color: #f39c12; }
        #clearItemsButton:hover { background-color: #e67e22; }
        #clearInvoiceButton { background-color: #e74c3c; }
        #clearInvoiceButton:hover { background-color: #c0392b; }
        #printButton { background-color: #34495e; }
        #printButton:hover { background-color: #2c3e50; }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            text-align: right;
            table-layout: fixed;
            font-size: 8px;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 3px;
            text-align: right;
            overflow: hidden;
            word-wrap: break-word;
        }

        th {
            background-color: #ecf0f1;
            color: #2c3e50;
        }

        #invoiceTable th {
            background-color: #e6f7ff;
        }

        #invoiceTable tfoot {
            font-weight: bold;
        }

        .payment-info td {
            background-color: #f9f9f9;
        }

        .action-buttons {
            display: flex;
            justify-content: center;
            gap: 2px;
        }

        .action-buttons button {
            width: auto;
            padding: 2px 4px;
            font-size: 7px;
            margin: 0;
        }

        .edit-stored-btn { background-color: #f39c12; }
        .edit-stored-btn:hover { background-color: #e67e22; }
        .delete-stored-btn { background-color: #e74c3c; }
        .delete-stored-btn:hover { background-color: #c0392b; }

        .edit-btn { background-color: #f39c12; }
        .edit-btn:hover { background-color: #e67e22; }
        .delete-btn { background-color: #e74c3c; }
        .delete-btn:hover { background-color: #c0392b; }

        .invoice-header {
            text-align: center;
            border-bottom: 1px solid #ddd;
            margin-bottom: 5px;
            padding-bottom: 5px;
        }

        .invoice-header-content p {
            margin: 2px 0;
            text-align: center;
        }
        
        #searchItemsInput {
            margin-bottom: 5px;
        }

        /* تنسيقات Select2 لتتناسب مع التصميم */
        .select2-container .select2-selection--single {
            height: 25px;
            padding: 3px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 23px;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 18px;
            padding-right: 8px; /* لتحسين المسافة من اليمين */
        }
        .select2-container .select2-selection--single .select2-selection__clear {
            float: left; /* لتحسين موضع زر المسح */
        }
        .select2-dropdown {
            font-size: 9px;
            text-align: right;
            direction: rtl;
        }
        .select2-search__field {
            font-size: 9px;
        }
        
        /* تنسيقات خاصة بالطباعة */
        @media print {
            .print-hide {
                display: none !important;
            }
            .print-only {
                display: block !important;
            }
            body {
                background-color: #fff;
                padding: 0;
                margin: 0;
                font-size: 12px;
            }
            .main-header, .top-container {
                display: none;
            }
            .bottom-container {
                height: auto;
                padding: 10px;
                box-shadow: none;
                width: 100%;
            }
            .card.invoice-container {
                box-shadow: none;
                max-height: none;
            }
            .card-content {
                overflow-y: visible;
                padding-bottom: 0;
            }
            .invoice-header {
                border-bottom: 2px solid #000;
                margin-bottom: 15px;
            }
            .invoice-header h1 {
                font-size: 20px;
            }
            .invoice-header p {
                font-size: 14px;
            }
            .invoice-container .print-only {
                display: block;
            }
            table {
                font-size: 12px;
            }
            th, td {
                padding: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="main-header print-hide">
        <h1>المحمود لكافة المواد الغذائية</h1>
    </div>

    <div class="top-container">
        <div class="card invoice-form-container print-hide">
            <div class="card-content">
                <h2>إضافة للفاتورة</h2>
                <form action="index.php" method="POST" id="invoiceHeaderForm">
                    <input type="hidden" name="action" value="update_invoice_info">
                    <label for="invoiceNumberInput">رقم الفاتورة:</label>
                    <input type="text" id="invoiceNumberInput" name="invoiceNumber" value="<?= htmlspecialchars($current_invoice['invoice_number']) ?>">
                    <label for="customerName">اسم الزبون:</label>
                    <input type="text" id="customerName" name="customerName" value="<?= htmlspecialchars($current_invoice['customer_name']) ?>">
                    <button type="submit" style="display: none;">حفظ معلومات الفاتورة</button>
                </form>
                <hr>
                <form action="index.php" method="POST" id="addToInvoiceForm">
                    <input type="hidden" name="action" value="add_to_invoice">
                    <label for="productNameSelect">الصنف:</label>
                    <select id="productNameSelect" name="productName">
                        <option></option>
                        <?php foreach ($items as $item): ?>
                            <option value="<?= htmlspecialchars($item['name']) ?>" data-price="<?= htmlspecialchars($item['price']) ?>"><?= htmlspecialchars($item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="quantity">عدد القطع:</label>
                    <input type="number" id="quantity" name="quantity" min="1" value="1" oninput="updateInvoicePreview()">
                    <label for="unit">الوحدة:</label>
                    <input type="text" id="unit" name="unit" oninput="updateInvoicePreview()">
                    <label for="price">سعر الوحدة ($):</label>
                    <input type="number" id="price" name="price" min="0.01" step="0.01" readonly>
                    <label for="notes">ملاحظة:</label>
                    <input type="text" id="notes" name="notes" oninput="updateInvoicePreview()">
                    <button type="submit">إضافة صنف للفاتورة</button>
                </form>
                <div class="button-group">
                    <form action="index.php" method="POST" style="display:inline;">
                        <input type="hidden" name="action" value="clear_invoice">
                        <button id="clearInvoiceButton" type="submit" onclick="return confirm('هل أنت متأكد من مسح الفاتورة؟')">مسح الفاتورة</button>
                    </form>
                    <button id="printButton" onclick="window.print()">طباعة الفاتورة</button>
                </div>
            </div>
        </div>

        <div class="card item-manager print-hide">
            <div class="card-content">
                <h2>إدارة الأصناف والأسعار</h2>
                <form action="index.php" method="POST" id="itemForm">
                    <input type="hidden" name="action" value="save_item">
                    <input type="hidden" name="editingItemId" id="editingItemId">
                    <label for="newItemName">الصنف:</label>
                    <input type="text" id="newItemName" name="newItemName">
                    <label for="newItemPrice">سعر الوحدة بالدولار:</label>
                    <input type="number" id="newItemPrice" name="newItemPrice" min="0.01" step="0.01">
                    <div class="button-group">
                        <button id="addItemBtn" type="submit">إضافة صنف</button>
                        <form action="index.php" method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="clear_items">
                            <button id="clearItemsButton" type="submit" onclick="return confirm('هل أنت متأكد من مسح قائمة الأصناف؟')">مسح الأصناف</button>
                        </form>
                    </div>
                </form>
                <hr>
                <h3>قائمة الأصناف المخزنة</h3>
                <input type="text" id="searchItemsInput" oninput="filterItems()" placeholder="بحث باسم الصنف...">
                <table id="itemsTable">
                    <thead>
                        <tr>
                            <th>الصنف</th>
                            <th>السعر</th>
                            <th>الخيارات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr data-name="<?= htmlspecialchars($item['name']) ?>">
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><?= number_format($item['price'], 2) ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="edit-stored-btn" onclick="editStoredItem('<?= htmlspecialchars($item['name']) ?>', '<?= htmlspecialchars($item['price']) ?>', '<?= htmlspecialchars($item['id']) ?>')">تعديل</button>
                                    <form action="index.php" method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_item">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                        <button class="delete-stored-btn" type="submit" onclick="return confirm('هل أنت متأكد من حذف هذا الصنف؟')">حذف</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="bottom-container">
        <div class="card invoice-container">
            <div id="invoiceHeader" class="invoice-header">
                <div class="print-only" style="display: none;">
                    <div style="text-align: center;">
                        <p style="font-size: 14px; margin-bottom: 0;">هاتف: +905364253975</p>
                        <h1 style="margin-top: 5px; font-size: 20px;">المحمود للمواد الغذائية</h1>
                    </div>
                    <div class="invoice-header-content">
                        <p><strong>رقم الفاتورة:</strong> <span id="printInvoiceNumber"><?= htmlspecialchars($current_invoice['invoice_number'] ?? '---') ?></span></p>
                        <p><strong>اسم الزبون:</strong> <span id="printCustomerName"><?= htmlspecialchars($current_invoice['customer_name'] ?? '---') ?></span></p>
                    </div>
                </div>
            </div>
            <div class="card-content">
                <table id="invoiceTable">
                    <thead>
                        <tr>
                            <th>ملاحظة</th>
                            <th>المجموع</th>
                            <th>السعر</th>
                            <th>الكمية</th>
                            <th>المادة</th>
                            <th class="print-hide">الخيارات</th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                        <?php foreach ($invoice_items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['notes']) ?></td>
                            <td><?= number_format($item['quantity'] * $item['price'], 2) ?></td>
                            <td><?= number_format($item['price'], 2) ?></td>
                            <td><?= htmlspecialchars($item['quantity'] . ' ' . $item['unit']) ?></td>
                            <td><?= htmlspecialchars($item['item_name']) ?></td>
                            <td class="print-hide">
                                <div class="action-buttons">
                                    <form action="index.php" method="POST">
                                        <input type="hidden" name="action" value="delete_invoice_item">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($item['id']) ?>">
                                        <button class="delete-btn" type="submit" onclick="return confirm('هل أنت متأكد من حذف هذا الصنف؟')">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    <tfoot>
                        <tr id="previewRow" style="color: #6c757d; background-color: #e9ecef; display: none;">
                            <td id="previewNotes"></td>
                            <td id="previewSubtotal"></td>
                            <td id="previewPrice"></td>
                            <td id="previewQuantity"></td>
                            <td id="previewName"></td>
                            <td class="print-hide"></td>
                        </tr>
                        <tr>
                            <td colspan="4">الإجمالي الكلي:</td>
                            <td colspan="2"><span id="totalAmount"><?= number_format($total_amount, 2) ?></span> دولار</td>
                        </tr>
                        <form action="index.php" method="POST" id="paidAmountForm">
                            <input type="hidden" name="action" value="update_invoice_info">
                            <tr class="payment-info">
                                <td colspan="4">المبلغ المدفوع:</td>
                                <td colspan="2"><input type="number" id="paidAmount" name="paidAmount" min="0" value="<?= htmlspecialchars($current_invoice['paid_amount'] ?? '0') ?>" onchange="document.getElementById('paidAmountForm').submit()"></td>
                            </tr>
                            <tr class="payment-info">
                                <td colspan="4">المبلغ المتبقي:</td>
                                <td colspan="2"><span id="remainingAmount"><?= number_format($remaining_amount, 2) ?></span> دولار</td>
                            </tr>
                        </form>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    function filterItems() {
        const searchTerm = document.getElementById('searchItemsInput').value.toLowerCase();
        const rows = document.querySelectorAll('#itemsTable tbody tr');
        rows.forEach(row => {
            const itemName = row.dataset.name.toLowerCase();
            if (itemName.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function initSelect2() {
        $('#productNameSelect').select2({
            placeholder: 'ابحث أو اختر صنفًا...',
            dir: "rtl"
        });
        $('#productNameSelect').on('change', function() {
            updatePrice();
        });
    }

    function updatePrice() {
        const selectedOption = $('#productNameSelect').find(':selected');
        const price = selectedOption.data('price') || '';
        document.getElementById('price').value = price;
        updateInvoicePreview();
    }

    function updateInvoicePreview() {
        const productName = $('#productNameSelect').val();
        const quantityToAdd = parseFloat(document.getElementById('quantity').value);
        const unit = document.getElementById('unit').value.trim();
        const notes = document.getElementById('notes').value.trim();
        const price = parseFloat(document.getElementById('price').value);
        
        const previewRow = document.getElementById('previewRow');
        
        if (productName && !isNaN(quantityToAdd) && quantityToAdd > 0 && price) {
            const subtotal = quantityToAdd * price;
            
            document.getElementById('previewName').textContent = productName;
            document.getElementById('previewQuantity').textContent = `${quantityToAdd} ${unit}`;
            document.getElementById('previewPrice').textContent = price.toFixed(2);
            document.getElementById('previewSubtotal').textContent = subtotal.toFixed(2);
            document.getElementById('previewNotes').textContent = notes;
            previewRow.style.display = '';
        } else {
            previewRow.style.display = 'none';
        }
    }
    
    function editStoredItem(name, price, id) {
        document.getElementById('newItemName').value = name;
        document.getElementById('newItemPrice').value = price;
        document.getElementById('editingItemId').value = id;
        document.getElementById('addItemBtn').textContent = 'تحديث الصنف';
        document.getElementById('newItemName').focus();
    }

    // لتحديث معلومات الفاتورة تلقائيًا عند الكتابة
    document.getElementById('invoiceNumberInput').addEventListener('input', function() {
        document.getElementById('invoiceHeaderForm').submit();
    });
    document.getElementById('customerName').addEventListener('input', function() {
        document.getElementById('invoiceHeaderForm').submit();
    });

    document.addEventListener('DOMContentLoaded', function() {
        initSelect2();
    });
</script>
</body>
</html>
