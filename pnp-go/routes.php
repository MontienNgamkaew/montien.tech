<?php

$pageController      = new PageController();
$publicController    = new PublicController();
$authController      = new AuthController();
$dashboardController = new DashboardController();
$vendorController    = new VendorController();
$vehicleController   = new VehicleController();
$reportController    = new ReportController();

$router->get('/', [$pageController, 'home']);
$router->get('/request', [$publicController, 'requestForm']);
$router->post('/request', [$publicController, 'submitRequest']);
$router->get('/status', [$publicController, 'statusForm']);
$router->post('/status', [$publicController, 'checkStatus']);
$router->get('/download', [$publicController, 'downloadPdf']);
$router->get('/vehicles', [$publicController, 'vehicleBoard']);
$router->get('/login', [$authController, 'loginForm']);
$router->post('/login', [$authController, 'login']);
$router->post('/logout', [$authController, 'logout']);
$router->get('/dashboard', [$dashboardController, 'index']);
$router->get('/dashboard/requisition', [$dashboardController, 'show']);
$router->post('/dashboard/requisition/approve', [$dashboardController, 'approve']);
$router->post('/dashboard/requisition/reject', [$dashboardController, 'reject']);
$router->get('/profile', [$dashboardController, 'profile']);
$router->post('/profile/signature', [$dashboardController, 'updateSignature']);
$router->get('/vendors', [$vendorController, 'index']);
$router->post('/vendors', [$vendorController, 'store']);
$router->post('/vendors/update', [$vendorController, 'update']);
$router->post('/vendors/delete', [$vendorController, 'delete']);
$router->get('/manage/vehicles', [$vehicleController, 'index']);
$router->post('/manage/vehicles', [$vehicleController, 'store']);
$router->post('/manage/vehicles/update', [$vehicleController, 'update']);
$router->post('/manage/vehicles/delete', [$vehicleController, 'delete']);
$router->get('/report', [$reportController, 'index']);
