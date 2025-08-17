    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Dashboard</h1>
        </div>

        <!-- Welcome Message -->
        <div class="alert alert-success">
            Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
        </div>

        <!-- Main Dashboard Cards -->
        <div class="row mt-4">

            <!-- Card 1: Sales & Orders -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-cart-check-fill"></i> Sales & Orders
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Order Management</h5>
                        <div class="d-grid gap-2 mt-3">
                            <a href="/modules/sales/entry_order.php" class="btn btn-success">
                                <i class="bi bi-cart-plus-fill"></i> New Order
                            </a>
                            <!-- CORRECTED: Link is now active and points to the correct location -->
                            <a href="/modules/sales/list_orders.php" class="btn btn-primary">
                                <i class="bi bi-search"></i> Find Orders
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Customer Management -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-people-fill"></i> Customer Management
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="d-grid gap-2 mt-3">
                            <a href="/modules/customer/entry_customer.php" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Add New Customer
                            </a>
                            <a href="/modules/customer/list_customers.php" class="btn btn-primary">
                                <i class="bi bi-search"></i> Find Customer
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Inventory -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-boxes"></i> Inventory
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Manage Products</h5>
                        <div class="d-grid gap-2 mt-3">
                            <a href="/modules/inventory/entry_item.php" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Add New Item
                            </a>
                            <a href="/modules/inventory/entry_grn.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-down"></i> Receive Stock (GRN)
                            </a>
                            <a href="/modules/inventory/list_stock.php" class="btn btn-secondary">
                                <i class="bi bi-card-list"></i> View Stock Levels
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Card 4: Price Calculator -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <i class="bi bi-calculator-fill"></i> Price Calculator
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">Pricing Tools</h5>
                        <div class="d-grid gap-2 mt-3">
                            <a href="/modules/price/calculate_price.php" class="btn btn-primary">
                                <i class="bi bi-play-circle"></i> Open Calculator
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div> <!-- End of the row -->

    </main>
</div>