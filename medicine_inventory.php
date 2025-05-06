<?php
require_once 'config.php';


// Verify user has permission (doctors and admins only)
if ($_SESSION['role'] !== 'doctor' && $_SESSION['role'] !== 'admin') {
    header('Location: unauthorized.php');
    exit();
}

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_low_stock = isset($_GET['low_stock']) ? true : false;
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$order = isset($_GET['order']) ? $_GET['order'] : 'asc';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Validate sort column
$allowed_sort = ['name', 'manufacturer', 'stock', 'price'];
$sort = in_array($sort, $allowed_sort) ? $sort : 'name';

// Validate order direction
$order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

try {
    // Base query
    $query = "SELECT * FROM Medicines";
    $count_query = "SELECT COUNT(*) FROM Medicines";
    $params = [];
    $where = [];

    // Apply search filter
    if (!empty($search)) {
        $where[] = "(name LIKE :search OR manufacturer LIKE :search OR dosage_form LIKE :search)";
        $params[':search'] = "%$search%";
    }

    // Apply low stock filter
    if ($filter_low_stock) {
        $where[] = "stock < 10";
    }

    // Combine WHERE clauses
    if (!empty($where)) {
        $query .= " WHERE " . implode(" AND ", $where);
        $count_query .= " WHERE " . implode(" AND ", $where);
    }

    // Get total count
    $stmt = $pdo->prepare($count_query);
    $stmt->execute($params);
    $total_medicines = $stmt->fetchColumn();
    $total_pages = ceil($total_medicines / $per_page);

    // Add sorting and pagination
    $query .= " ORDER BY $sort $order LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);

    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $medicines = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Failed to load medicine inventory. Please try again later.";
}

include 'navbar.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3><i class="fas fa-pills me-2"></i> Medicine Inventory</h3>
                        <div>
                            <a href="medicine_add.php" class="btn btn-light btn-sm me-2">
                                <i class="fas fa-plus me-1"></i> Add Medicine
                            </a>
                            <a href="medicine_import.php" class="btn btn-light btn-sm">
                                <i class="fas fa-file-import me-1"></i> Import
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Filters and Search -->
                    <form method="get" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search medicines..." value="<?= htmlspecialchars($search) ?>">
                                    <button class="btn btn-primary" type="submit">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="lowStockFilter" 
                                           name="low_stock" <?= $filter_low_stock ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="lowStockFilter">
                                        Show low stock only (<10)
                                    </label>
                                </div>
                            </div>
                        </div>
                        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                        <input type="hidden" name="order" value="<?= htmlspecialchars(strtolower($order)) ?>">
                    </form>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php else: ?>
                        <!-- Inventory Table -->
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>
                                            <a href="?search=<?= urlencode($search) ?>&sort=name&order=<?= $sort === 'name' && $order === 'ASC' ? 'desc' : 'asc' ?><?= $filter_low_stock ? '&low_stock=1' : '' ?>">
                                                Name
                                                <?= $sort === 'name' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </a>
                                        </th>
                                        <th>
                                            <a href="?search=<?= urlencode($search) ?>&sort=manufacturer&order=<?= $sort === 'manufacturer' && $order === 'ASC' ? 'desc' : 'asc' ?><?= $filter_low_stock ? '&low_stock=1' : '' ?>">
                                                Manufacturer
                                                <?= $sort === 'manufacturer' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </a>
                                        </th>
                                        <th>Dosage Form</th>
                                        <th class="text-end">
                                            <a href="?search=<?= urlencode($search) ?>&sort=price&order=<?= $sort === 'price' && $order === 'ASC' ? 'desc' : 'asc' ?><?= $filter_low_stock ? '&low_stock=1' : '' ?>">
                                                Price
                                                <?= $sort === 'price' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </a>
                                        </th>
                                        <th class="text-end">
                                            <a href="?search=<?= urlencode($search) ?>&sort=stock&order=<?= $sort === 'stock' && $order === 'ASC' ? 'desc' : 'asc' ?><?= $filter_low_stock ? '&low_stock=1' : '' ?>">
                                                Stock
                                                <?= $sort === 'stock' ? ($order === 'ASC' ? '↑' : '↓') : '' ?>
                                            </a>
                                        </th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($medicines as $medicine): ?>
                                        <tr class="<?= $medicine['stock'] < 10 ? 'table-warning' : '' ?>">
                                            <td>
                                                <strong><?= htmlspecialchars($medicine['name']) ?></strong>
                                                <?php if ($medicine['stock'] <= 0): ?>
                                                    <span class="badge bg-danger ms-2">Out of Stock</span>
                                                <?php elseif ($medicine['stock'] < 5): ?>
                                                    <span class="badge bg-warning ms-2">Low Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($medicine['manufacturer']) ?></td>
                                            <td><?= htmlspecialchars($medicine['dosage_form']) ?></td>
                                            <td class="text-end">$<?= number_format($medicine['price'], 2) ?></td>
                                            <td class="text-end">
                                                <span class="<?= $medicine['stock'] < 10 ? 'fw-bold text-danger' : '' ?>">
                                                    <?= $medicine['stock'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <a href="medicine_view.php?id=<?= $medicine['medicine_id'] ?>" 
                                                       class="btn btn-outline-primary" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="medicine_edit.php?id=<?= $medicine['medicine_id'] ?>" 
                                                       class="btn btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="medicine_adjust.php?id=<?= $medicine['medicine_id'] ?>" 
                                                       class="btn btn-outline-info" title="Adjust Stock">
                                                        <i class="fas fa-calculator"></i>
                                                    </a>
                                                    <a href="medicine_delete.php?id=<?= $medicine['medicine_id'] ?>" 
                                                       class="btn btn-outline-danger" title="Delete"
                                                       onclick="return confirm('Are you sure you want to delete this medicine?');">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?search=<?= urlencode($search) ?>&page=<?= $page-1 ?>&sort=<?= $sort ?>&order=<?= strtolower($order) ?><?= $filter_low_stock ? '&low_stock=1' : '' ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" 
                                               href="?search=<?= urlencode($search) ?>&page=<?= $i ?>&sort=<?= $sort ?>&order=<?= strtolower($order) ?><?= $filter_low_stock ? '&low_stock=1' : '' ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?search=<?= urlencode($search) ?>&page=<?= $page+1 ?>&sort=<?= $sort ?>&order=<?= strtolower($order) ?><?= $filter_low_stock ? '&low_stock=1' : '' ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="card-footer bg-light">
                    <div class="row">
                        <div class="col-md-6">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#exportModal">
                                <i class="fas fa-file-export me-1"></i> Export Inventory
                            </button>
                        </div>
                        <div class="col-md-6 text-end">
                            <span class="text-muted">
                                Showing <?= count($medicines) ?> of <?= $total_medicines ?> medicines
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Inventory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="medicine_export.php">
                    <div class="mb-3">
                        <label for="exportFormat" class="form-label">Format</label>
                        <select class="form-select" id="exportFormat" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="includeAll" name="include_all" checked>
                        <label class="form-check-label" for="includeAll">Include all medicines (ignore current filters)</label>
                    </div>
                    <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                    <input type="hidden" name="low_stock" value="<?= $filter_low_stock ? '1' : '0' ?>">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Export</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>