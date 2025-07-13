<?php

// File: /modules/customer/list_customers.php

define('_IN_APP_', true);
session_start(); // This must be first!
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_login();

$page_title = "Customer List";
$breadcrumbs = [
    'Dashboard' => '/index.php',
    'Customers' => ''
];

require_once __DIR__ . '/../../includes/header.php';

// Pagination
$page = max(1, $_GET['page'] ?? 1);
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get customers
$db = db();
$stmt = $db->prepare("SELECT SQL_CALC_FOUND_ROWS customer_id, name, phone, city FROM customers ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get total count
$total = $db->query("SELECT FOUND_ROWS()")->fetch_row()[0];
$total_pages = ceil($total / $per_page);
?>

<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Customer List</h1>
        <a href="entry_customer.php" class="btn btn-success">
            <i class="bi bi-plus"></i> New Customer
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th><input type="text" class="form-control form-control-sm live-search" data-column="customer_id" placeholder="Search ID"></th>
                            <th><input type="text" class="form-control form-control-sm live-search" data-column="name" placeholder="Search Name"></th>
                            <th><input type="text" class="form-control form-control-sm live-search" data-column="phone" placeholder="Search Phone"></th>
                            <th><input type="text" class="form-control form-control-sm live-search" data-column="city" placeholder="Search City"></th>
                            <th></th>
                        </tr>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>City</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $customer): ?>
                        <tr>
                            <td><?= htmlspecialchars($customer['customer_id']) ?></td>
                            <td><?= htmlspecialchars($customer['name']) ?></td>
                            <td><?= htmlspecialchars($customer['phone']) ?></td>
                            <td><?= htmlspecialchars($customer['city']) ?></td>
                            <td>
                                <a href="entry_customer.php?customer_id=<?= $customer['customer_id'] ?>" 
                                   class="btn btn-sm btn-outline-primary customer-edit-btn">
                                    <i class="bi bi-pencil"></i> Edit
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <script src="/assets/js/app.js"></script>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center mt-4">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page-1 ?>">Previous</a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page+1 ?>">Next</a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>