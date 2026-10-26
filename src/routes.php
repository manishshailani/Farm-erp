<?php

declare(strict_types=1);

use App\Controllers\Admin\AuditController;
use App\Controllers\Admin\CustomerController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\EmployeeController;
use App\Controllers\Admin\FinanceController;
use App\Controllers\Admin\InventoryController;
use App\Controllers\Admin\LivestockController;
use App\Controllers\Admin\OrchardController;
use App\Controllers\Admin\OrderController;
use App\Controllers\Admin\PondController;
use App\Controllers\Admin\ReportController;
use App\Controllers\Admin\RfidController;
use App\Controllers\Admin\UserController;
use App\Controllers\Auth\AuthController;
use App\Controllers\Shop\AccountController;
use App\Controllers\Shop\CartController;
use App\Controllers\Shop\CatalogueController;
use App\Controllers\Shop\CheckoutController;
use App\Controllers\Shop\ShopAuth;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Core\Redirect;
use App\Core\Request;
use App\Core\Router;

$router = new Router();
$router->middleware(Csrf::middleware());

// ---------------------------------------------------------------- Public
$router->get('/', static function (Request $r, array $a): void {
    Redirect::to('/shop');
});

// ------------------------------------------------------------ Staff auth
$auth = new AuthController();
$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login']);
$router->post('/logout', [$auth, 'logout'], [Auth::middleware()]);

// --------------------------------------------------------- Admin portal
$adminAuth = [Auth::middleware()];

$dashboard = new DashboardController();
$router->get('/dashboard', [$dashboard, 'index'], $adminAuth);

$livestock = new LivestockController();
$router->get('/livestock', [$livestock, 'index'], array_merge($adminAuth, [Permission::middleware('livestock.view')]));
$router->get('/livestock/create', [$livestock, 'create'], array_merge($adminAuth, [Permission::middleware('livestock.manage')]));
$router->post('/livestock', [$livestock, 'store'], array_merge($adminAuth, [Permission::middleware('livestock.manage')]));
$router->get('/livestock/{id}', [$livestock, 'show'], array_merge($adminAuth, [Permission::middleware('livestock.view')]));
$router->post('/livestock/{id}/weight', [$livestock, 'storeWeight'], array_merge($adminAuth, [Permission::middleware('livestock.manage')]));
$router->post('/livestock/{id}/vaccination', [$livestock, 'storeVaccination'], array_merge($adminAuth, [Permission::middleware('livestock.vaccination.manage')]));
$router->post('/livestock/{id}/tag', [$livestock, 'storeTag'], array_merge($adminAuth, [Permission::middleware('livestock.rfid.manage')]));
$router->post('/livestock/{id}/dispose', [$livestock, 'dispose'], array_merge($adminAuth, [Permission::middleware('livestock.manage')]));
$router->get('/api/species/{speciesId}/breeds', [$livestock, 'breedsForSpecies'], $adminAuth);

$rfid = new RfidController();
$router->get('/livestock-rfid/scan', [$rfid, 'scanForm'], array_merge($adminAuth, [Permission::middleware('livestock.rfid.scan')]));
$router->post('/livestock-rfid/scan', [$rfid, 'scan'], array_merge($adminAuth, [Permission::middleware('livestock.rfid.scan')]));

$inventory = new InventoryController();
$router->get('/inventory', [$inventory, 'index'], array_merge($adminAuth, [Permission::middleware('inventory.view')]));
$router->post('/inventory', [$inventory, 'store'], array_merge($adminAuth, [Permission::middleware('inventory.manage')]));
$router->get('/inventory/{id}', [$inventory, 'show'], array_merge($adminAuth, [Permission::middleware('inventory.view')]));
$router->post('/inventory/{id}/receive', [$inventory, 'receive'], array_merge($adminAuth, [Permission::middleware('inventory.manage')]));
$router->post('/inventory/{id}/adjust', [$inventory, 'adjust'], array_merge($adminAuth, [Permission::middleware('inventory.manage')]));

$orchard = new OrchardController();
$router->get('/orchard', [$orchard, 'index'], array_merge($adminAuth, [Permission::middleware('orchard.view')]));
$router->post('/orchard/blocks', [$orchard, 'storeBlock'], array_merge($adminAuth, [Permission::middleware('orchard.manage')]));
$router->get('/orchard/{id}', [$orchard, 'show'], array_merge($adminAuth, [Permission::middleware('orchard.view')]));
$router->post('/orchard/{id}/trees', [$orchard, 'storeTree'], array_merge($adminAuth, [Permission::middleware('orchard.manage')]));
$router->post('/orchard/{id}/trees/{treeId}/status', [$orchard, 'updateTreeStatus'], array_merge($adminAuth, [Permission::middleware('orchard.manage')]));
$router->post('/orchard/{id}/harvests', [$orchard, 'storeHarvest'], array_merge($adminAuth, [Permission::middleware('orchard.manage')]));

$pond = new PondController();
$router->get('/pond', [$pond, 'index'], array_merge($adminAuth, [Permission::middleware('pond.view')]));
$router->post('/pond/ponds', [$pond, 'storePond'], array_merge($adminAuth, [Permission::middleware('pond.manage')]));
$router->get('/pond/{id}', [$pond, 'show'], array_merge($adminAuth, [Permission::middleware('pond.view')]));
$router->post('/pond/{id}/stocking', [$pond, 'storeStockingBatch'], array_merge($adminAuth, [Permission::middleware('pond.manage')]));
$router->post('/pond/{id}/stocking/{batchId}/harvest', [$pond, 'harvestBatch'], array_merge($adminAuth, [Permission::middleware('pond.manage')]));
$router->post('/pond/{id}/stocking/{batchId}/lost', [$pond, 'loseBatch'], array_merge($adminAuth, [Permission::middleware('pond.manage')]));
$router->post('/pond/{id}/water-quality', [$pond, 'storeWaterQualityReading'], array_merge($adminAuth, [Permission::middleware('pond.manage')]));

$employees = new EmployeeController();
$router->get('/employees', [$employees, 'index'], array_merge($adminAuth, [Permission::middleware('employees.view')]));
$router->post('/employees', [$employees, 'store'], array_merge($adminAuth, [Permission::middleware('employees.manage')]));
$router->post('/employees/tasks', [$employees, 'storeTask'], array_merge($adminAuth, [Permission::middleware('tasks.manage')]));
$router->post('/employees/tasks/{id}/status', [$employees, 'updateTaskStatus'], array_merge($adminAuth, [Permission::middleware('tasks.manage')]));

$customers = new CustomerController();
$router->get('/customers', [$customers, 'index'], array_merge($adminAuth, [Permission::middleware('customers.view')]));
$router->post('/customers', [$customers, 'store'], array_merge($adminAuth, [Permission::middleware('customers.manage')]));
$router->get('/customers/{id}', [$customers, 'show'], array_merge($adminAuth, [Permission::middleware('customers.view')]));

$orders = new OrderController();
$router->get('/orders', [$orders, 'index'], array_merge($adminAuth, [Permission::middleware('orders.view')]));
$router->get('/orders/{id}', [$orders, 'show'], array_merge($adminAuth, [Permission::middleware('orders.view')]));
$router->post('/orders/{id}/transition', [$orders, 'transition'], array_merge($adminAuth, [Permission::middleware('orders.manage')]));
$router->post('/orders/{id}/payments', [$orders, 'recordPayment'], array_merge($adminAuth, [Permission::middleware('payments.record')]));

$finance = new FinanceController();
$router->get('/finance', [$finance, 'index'], array_merge($adminAuth, [Permission::middleware('finance.view')]));
$router->post('/finance', [$finance, 'store'], array_merge($adminAuth, [Permission::middleware('finance.manage')]));

$users = new UserController();
$router->get('/users', [$users, 'index'], array_merge($adminAuth, [Permission::middleware('users.manage')]));
$router->post('/users', [$users, 'store'], array_merge($adminAuth, [Permission::middleware('users.manage')]));
$router->post('/users/{id}/toggle-active', [$users, 'toggleActive'], array_merge($adminAuth, [Permission::middleware('users.manage')]));
$router->post('/users/{id}/roles', [$users, 'updateRoles'], array_merge($adminAuth, [Permission::middleware('roles.manage')]));
$router->post('/users/{id}/password', [$users, 'resetPassword'], array_merge($adminAuth, [Permission::middleware('users.manage')]));

// Self-service password change -- any authenticated staff account, not
// gated by 'users.manage' (that permission governs managing OTHER
// accounts; every signed-in user may change their own password).
$router->get('/account/password', [$users, 'showChangePassword'], $adminAuth);
$router->post('/account/password', [$users, 'updateOwnPassword'], $adminAuth);

$audit = new AuditController();
$router->get('/audit', [$audit, 'index'], array_merge($adminAuth, [Permission::middleware('audit.view')]));

$reports = new ReportController();
$router->get('/reports', [$reports, 'index'], array_merge($adminAuth, [Permission::middleware('reports.view')]));
$router->get('/reports/livestock-register', [$reports, 'livestockRegister'], array_merge($adminAuth, [Permission::middleware('reports.view')]));
$router->get('/reports/vaccination-due', [$reports, 'vaccinationDue'], array_merge($adminAuth, [Permission::middleware('reports.view')]));
$router->get('/reports/inventory-stock', [$reports, 'inventoryStock'], array_merge($adminAuth, [Permission::middleware('reports.view')]));
$router->get('/reports/sales-register', [$reports, 'salesRegister'], array_merge($adminAuth, [Permission::middleware('reports.view')]));
$router->get('/reports/income-expense', [$reports, 'incomeExpense'], array_merge($adminAuth, [Permission::middleware('reports.view')]));
$router->get('/reports/receivables', [$reports, 'receivables'], array_merge($adminAuth, [Permission::middleware('reports.view')]));

// ---------------------------------------------------- Public shop + customer portal
$catalogue = new CatalogueController();
$router->get('/shop', [$catalogue, 'index']);
$router->get('/shop/products/{id}', [$catalogue, 'show']);

$shopAccount = new AccountController();
$router->get('/shop/register', [$shopAccount, 'showRegister']);
$router->post('/shop/register', [$shopAccount, 'register']);
$router->get('/shop/login', [$shopAccount, 'showLogin']);
$router->post('/shop/login', [$shopAccount, 'login']);
$router->post('/shop/logout', [$shopAccount, 'logout'], [Auth::middleware()]);

$customerAuth = [Auth::middleware(), ShopAuth::middleware()];
$router->get('/shop/account/orders', [$shopAccount, 'orders'], $customerAuth);
$router->get('/shop/account/orders/{id}', [$shopAccount, 'orderDetail'], $customerAuth);
$router->get('/shop/account/addresses', [$shopAccount, 'addresses'], $customerAuth);
$router->post('/shop/account/addresses', [$shopAccount, 'storeAddress'], $customerAuth);
$router->get('/shop/account/password', [$shopAccount, 'showChangePassword'], $customerAuth);
$router->post('/shop/account/password', [$shopAccount, 'updatePassword'], $customerAuth);

$cart = new CartController();
$router->get('/shop/cart', [$cart, 'show'], $customerAuth);
$router->post('/shop/cart/add', [$cart, 'add'], $customerAuth);
$router->post('/shop/cart/{itemId}/update', [$cart, 'update'], $customerAuth);
$router->post('/shop/cart/{itemId}/remove', [$cart, 'remove'], $customerAuth);

$checkout = new CheckoutController();
$router->get('/shop/checkout', [$checkout, 'show'], $customerAuth);
$router->post('/shop/checkout', [$checkout, 'placeOrder'], $customerAuth);

$router->dispatch(Request::fromGlobals());
