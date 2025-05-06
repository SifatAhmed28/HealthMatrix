<?php
require_once 'config.php';
require_once 'includes/auth_check.php';

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Get diseases from database
try {
    $query = "SELECT * FROM Diseases";
    $params = [];
    
    if (!empty($search)) {
        $query .= " WHERE name LIKE :search OR symptoms LIKE :search";
        $params[':search'] = "%$search%";
    }
    
    // Get total count for pagination
    $count_stmt = $pdo->prepare(str_replace('*', 'COUNT(*)', $query));
    $count_stmt->execute($params);
    $total_diseases = $count_stmt->fetchColumn();
    $total_pages = ceil($total_diseases / $per_page);
    
    // Get paginated results
    $query .= " ORDER BY name LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    $diseases = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $error = "Failed to load disease information. Please try again later.";
}

include 'navbar.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h3><i class="fas fa-book-medical me-2"></i> Disease Library</h3>
                        <?php if ($_SESSION['role'] === 'doctor'): ?>
                            <a href="disease_add.php" class="btn btn-light btn-sm">
                                <i class="fas fa-plus me-1"></i> Add New
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Search Form -->
                    <form method="get" class="mb-4">
                        <div class="input-group">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search diseases..." value="<?= htmlspecialchars($search) ?>">
                            <button class="btn btn-primary" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (!empty($search)): ?>
                                <a href="disease_library.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                    
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php elseif (empty($diseases)): ?>
                        <div class="alert alert-info">
                            No diseases found. <?php if (!empty($search)): ?>Try a different search term.<?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- Disease List -->
                        <div class="list-group">
                            <?php foreach ($diseases as $disease): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="mb-1">
                                                <a href="disease_view.php?id=<?= $disease['disease_id'] ?>" 
                                                   class="text-decoration-none">
                                                    <?= htmlspecialchars($disease['name']) ?>
                                                </a>
                                            </h5>
                                            <p class="mb-1 text-muted">
                                                <?= nl2br(htmlspecialchars(substr($disease['description'], 0, 150))) ?>
                                                <?= strlen($disease['description']) > 150 ? '...' : '' ?>
                                            </p>
                                        </div>
                                        <?php if ($_SESSION['role'] === 'doctor'): ?>
                                            <div class="btn-group btn-group-sm">
                                                <a href="disease_edit.php?id=<?= $disease['disease_id'] ?>" 
                                                   class="btn btn-outline-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="disease_delete.php?id=<?= $disease['disease_id'] ?>" 
                                                   class="btn btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to delete this disease?');">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mt-2">
                                        <span class="badge bg-info">
                                            <i class="fas fa-info-circle me-1"></i>
                                            <?= implode(', ', array_slice(explode(',', $disease['symptoms']), 0, 3)) ?>
                                            <?= count(explode(',', $disease['symptoms'])) > 3 ? '...' : '' ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?search=<?= urlencode($search) ?>&page=<?= $page-1 ?>">
                                                Previous
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" 
                                               href="?search=<?= urlencode($search) ?>&page=<?= $i ?>">
                                                <?= $i ?>
                                            </a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" 
                                               href="?search=<?= urlencode($search) ?>&page=<?= $page+1 ?>">
                                                Next
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>