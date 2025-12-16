<?php
require_once '../config.php';
requireAdmin();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'get_sales_trends':
                $stmt = $pdo->query("
                    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as order_count, SUM(total_amount) as revenue
                    FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC
                ");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                exit;
                
            case 'get_category_distribution':
                $stmt = $pdo->query("
                    SELECT c.name as category, COUNT(p.id) as product_count, SUM(p.stock_quantity) as total_stock
                    FROM categories c LEFT JOIN products p ON c.id = p.category_id
                    WHERE c.is_active = 1 GROUP BY c.id, c.name ORDER BY product_count DESC
                ");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                exit;
                
            case 'get_order_status':
                $stmt = $pdo->query("SELECT status, COUNT(*) as count, SUM(total_amount) as total FROM orders GROUP BY status");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                exit;
                
            case 'get_top_products':
                $stmt = $pdo->query("
                    SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.total_price) as revenue
                    FROM order_items oi JOIN products p ON oi.product_id = p.id
                    GROUP BY p.id, p.name ORDER BY total_sold DESC LIMIT 10
                ");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                exit;
                
            case 'export_analytics':
                $salesData = $pdo->query("
                    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as orders, SUM(total_amount) as revenue
                    FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) GROUP BY month ORDER BY month
                ")->fetchAll();
                
                $categoryData = $pdo->query("
                    SELECT c.name, COUNT(p.id) as products FROM categories c LEFT JOIN products p ON c.id = p.category_id GROUP BY c.name
                ")->fetchAll();
                
                logUserActivity($_SESSION['user_id'], 'analytics_export', 'Exported analytics report');
                
                echo json_encode(['success' => true, 'data' => ['sales' => $salesData, 'categories' => $categoryData]]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Get statistics
$stats = [];
$stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM orders WHERE payment_status = 'paid'");
$stats['total_revenue'] = $stmt->fetch()['total'];
$stats['total_orders'] = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$stats['total_customers'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$stats['total_products'] = $pdo->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn();

$stmt = $pdo->query("
    SELECT COALESCE(SUM(total_amount), 0) as monthly_revenue FROM orders 
    WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) AND payment_status = 'paid'
");
$stats['monthly_revenue'] = $stmt->fetch()['monthly_revenue'];

$stmt = $pdo->query("SELECT COALESCE(AVG(total_amount), 0) as avg FROM orders WHERE payment_status = 'paid'");
$stats['avg_order_value'] = $stmt->fetch()['avg'];

$stmt = $pdo->query("
    SELECT 
        COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(CURRENT_DATE()) THEN total_amount END), 0) as current_month,
        COALESCE(SUM(CASE WHEN MONTH(created_at) = MONTH(DATE_SUB(CURRENT_DATE(), INTERVAL 1 MONTH)) THEN total_amount END), 0) as previous_month
    FROM orders WHERE payment_status = 'paid' AND created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL 2 MONTH)
");
$growth = $stmt->fetch();
$stats['growth_rate'] = $growth['previous_month'] > 0 ? (($growth['current_month'] - $growth['previous_month']) / $growth['previous_month']) * 100 : 0;

$stmt = $pdo->query("
    SELECT c.name, COUNT(oi.id) as sales FROM order_items oi
    JOIN products p ON oi.product_id = p.id JOIN categories c ON p.category_id = c.id
    GROUP BY c.id, c.name ORDER BY sales DESC LIMIT 1
");
$topCategory = $stmt->fetch();
$stats['top_category'] = $topCategory ? $topCategory['name'] : 'N/A';

// Get admin details
$stmt = $pdo->prepare("SELECT first_name, last_name, avatar FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - <?php echo APP_NAME;?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Nunito:wght@200;300;400;500;600;700;800;900;1000&family=Roboto:wght@300;400;500;700&display=swap");
        *,*::before,*::after{box-sizing:border-box;padding:0;margin:0}
        nav ul,nav ul li{outline:0}
        nav ul li a{text-decoration:none}
        body{font-family:"Nunito",sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background-image:url(https://github.com/ecemgo/mini-samples-great-tricks/assets/13468728/5baf8325-ed69-40b0-b9d2-d8c5d2bde3b0);background-repeat:no-repeat;background-size:cover}
        main{display:grid;grid-template-columns:13% 87%;width:100%;margin:40px;background:rgb(254,254,254);box-shadow:0 .5px 0 1px rgba(255,255,255,.23)inset,0 1px 0 0 rgba(255,255,255,.66)inset,0 4px 16px rgba(0,0,0,.12);border-radius:15px;z-index:10}
        .main-menu{overflow:hidden;background:rgb(73,57,113);padding-top:10px;border-radius:15px 0 0 15px;padding-bottom: 20px;}
        .main-menu h1 {display: block;font-size: 1.5rem;font-weight: 500; text-align: center; margin: 0;color: #fff;font-family: "Nunito", sans-serif;padding-top: 20px;}
        .main-menu small {display: block;font-size: 1rem;font-weight: 300;text-align: center;margin: 10px 0;color: #fff; }                .logo{display:none;width:30px;margin:20px auto}
        .nav-item{position:relative;display:block}
        .nav-item a{position:relative;display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;padding:15px 0;margin-left:10px;border-top-left-radius:20px;border-bottom-left-radius:20px}
        .nav-item b:nth-child(1),.nav-item b:nth-child(2){position:absolute;height:15px;width:100%;background:#fff;display:none}
        .nav-item b:nth-child(1){top:-15px}
        .nav-item b:nth-child(2){bottom:-15px}
        .nav-item b:nth-child(1)::before,.nav-item b:nth-child(2)::before{content:"";position:absolute;top:0;left:0;width:100%;height:100%;background:rgb(73,57,113)}
        .nav-item b:nth-child(1)::before{border-bottom-right-radius:20px}
        .nav-item b:nth-child(2)::before{border-top-right-radius:20px}
        .nav-item.active b:nth-child(1),.nav-item.active b:nth-child(2){display:block}
        .nav-item.active a{color:#000;background:rgb(254,254,254)}
        .nav-icon{width:60px;height:20px;font-size:20px;text-align:center}
        .nav-text{display:block;width:120px;height:20px}
        .notification-badge{position:absolute;top:10px;right:20px;background:#ef4444;color:white;border-radius:50%;padding:2px 6px;font-size:11px;font-weight:700}
        .content{background:#f6f7fb;padding:20px;border-radius:0 15px 15px 0;overflow-y:auto;max-height:calc(100vh - 80px)}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .header h1{font-size:1.8rem;font-weight:700;color:#484d53}
        .user-section{display:flex;align-items:center;gap:15px}
        .user-avatar{width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,rgb(124,136,224),#c3f4fc);display:flex;align-items:center;justify-content:center;font-weight:700;color:white;font-size:16px}
        .btn{padding:10px 20px;border:none;border-radius:12px;font-weight:600;cursor:pointer;transition:all .3s ease;font-size:.9rem}
        .btn-primary{background:rgb(73,57,113);color:white}
        .btn-primary:hover{background:rgb(93,77,133);transform:translateY(-2px)}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:20px}
        .stat-card{background:white;padding:20px;border-radius:15px;box-shadow:rgba(0,0,0,.16)0 1px 3px;transition:transform .3s ease}
        .stat-card:hover{transform:translateY(-5px)}
        .stat-card.revenue{background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white}
        .stat-card.orders{background:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);color:white}
        .stat-card.customers{background:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);color:white}
        .stat-card.products{background:linear-gradient(135deg,#43e97b 0%,#38f9d7 100%);color:white}
        .stat-card i{font-size:2rem;margin-bottom:10px}
        .stat-card h6{font-size:.9rem;font-weight:600;margin-bottom:5px;opacity:.9}
        .stat-card h3{font-size:2rem;font-weight:700;margin:5px 0}
        .stat-card small{font-size:.85rem;opacity:.85}
        .growth-badge{padding:4px 10px;border-radius:12px;font-size:.75rem;font-weight:600;display:inline-block;margin-top:5px}
        .growth-positive{background:rgba(16,185,129,.2);color:#10b981}
        .growth-negative{background:rgba(239,68,68,.2);color:#ef4444}
        .chart-section{background:white;padding:20px;border-radius:15px;box-shadow:rgba(0,0,0,.16)0 1px 3px;margin-bottom:20px}
        .chart-section h2{font-size:1.2rem;font-weight:700;color:#484d53;margin-bottom:15px}
        .charts-grid{display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px}
        .charts-grid-equal{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}
         /* lil style glow for the alert */
  .swal2-popup {
    border-radius: 15px !important;
    box-shadow: 0 8px 25px rgba(73, 57, 113, 0.3);
  }

  .swal2-confirm.swal-confirm {
    background: linear-gradient(135deg, rgb(73, 57, 113), #a38cd9) !important;
    border: none !important;
    font-weight: 600;
  }

  .swal2-cancel.swal-cancel {
    background: #f6f7fb !important;
    color: #484d53 !important;
    border: 1px solid #ddd !important;
  }

  .swal2-confirm:hover, .swal2-cancel:hover {
    transform: translateY(-1px);
  }
        @media(max-width:1500px){main{grid-template-columns:6% 94%}.main-menu h1{display:none}.logo{display:block}.nav-text{display:none}}
        @media(max-width:910px){main{grid-template-columns:10% 90%;margin:20px}.charts-grid{grid-template-columns:1fr}}
        @media(max-width:700px){main{grid-template-columns:15% 85%}.header{flex-direction:column;align-items:flex-start;gap:15px}.stats-grid{grid-template-columns:1fr}.charts-grid-equal{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <main>
        <nav class="main-menu">
            <h1><i class="fas fa-rocket" style="margin-right: 8px;"></i><?php echo APP_NAME;?></h1>
            <small>Admin Panel</small>
            <div class="logo"><i class="fa fa-rocket" style="font-size:24px;color:white"></i></div>
            <ul>
                <li class="nav-item"><b></b><b></b><a href="dashboard.php"><i class="fa fa-home nav-icon"></i><span class="nav-text">Dashboard</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="products.php"><i class="fa fa-box nav-icon"></i><span class="nav-text">Products</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="orders.php"><i class="fa fa-shopping-cart nav-icon"></i><span class="nav-text">Orders</span><?php if($pendingOrders>0):?><span class="notification-badge"><?php echo $pendingOrders;?></span><?php endif;?></a></li>
                <li class="nav-item"><b></b><b></b><a href="vendors.php"><i class="fa fa-users-cog nav-icon"></i><span class="nav-text">Vendors</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="users.php"><i class="fa fa-users nav-icon"></i><span class="nav-text">Users</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="delivery.php"><i class="fa fa-truck nav-icon"></i><span class="nav-text">Delivery</span></a></li>
                <li class="nav-item active"><b></b><b></b><a href="analytics.php"><i class="fa fa-chart-bar nav-icon"></i><span class="nav-text">Analytics</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="testimonials.php"><i class="fa fa-star nav-icon"></i><span class="nav-text">Testimonials</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="notifications.php"><i class="fa fa-bell nav-icon"></i><span class="nav-text">Notifications</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="contact.php"><i class="fa fa-envelope nav-icon"></i><span class="nav-text">Contact</span></a></li>
                <li class="nav-item"><b></b><b></b><a href="settings.php"><i class="fa fa-cog nav-icon"></i><span class="nav-text">Settings</span></a></li>
<li class="nav-item">
  <b></b>
  <b></b>
  <a href="#" onclick="confirmLogout(event)">
    <i class="fa fa-sign-out-alt nav-icon"></i>
    <span class="nav-text">Logout</span>
  </a>
</li>
    </nav> 

        <div class="content">
            <div class="header">
                <h1><i class="fas fa-chart-bar"></i> Analytics Dashboard</h1>
                <div class="user-section">
                    <button class="btn btn-primary" onclick="exportAnalytics()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <?php if(!empty($admin['avatar'])):?>
                        <img src="<?php echo htmlspecialchars($admin['avatar']);?>" alt="Admin" style="width:40px;height:40px;border-radius:50%;object-fit:cover">
                    <?php else:?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($admin['first_name'],0,1));?>
                        </div>
                    <?php endif;?>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card revenue">
                    <i class="fas fa-dollar-sign"></i>
                    <h6>Total Revenue</h6>
                    <h3>$<?php echo number_format($stats['total_revenue'],2);?></h3>
                    <span class="growth-badge <?php echo $stats['growth_rate']>=0?'growth-positive':'growth-negative';?>" style="color:white">
                        <i class="fas fa-arrow-<?php echo $stats['growth_rate']>=0?'up':'down';?>"></i>
                        <?php echo abs(round($stats['growth_rate'],1));?>%
                    </span>
                </div>
                <div class="stat-card orders">
                    <i class="fas fa-shopping-cart"></i>
                    <h6>Total Orders</h6>
                    <h3><?php echo number_format($stats['total_orders']);?></h3>
                    <small>All time orders</small>
                </div>
                <div class="stat-card customers">
                    <i class="fas fa-users"></i>
                    <h6>Total Customers</h6>
                    <h3><?php echo number_format($stats['total_customers']);?></h3>
                    <small>Registered users</small>
                </div>
                <div class="stat-card products">
                    <i class="fas fa-box"></i>
                    <h6>Active Products</h6>
                    <h3><?php echo number_format($stats['total_products']);?></h3>
                    <small>In stock items</small>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(250px,1fr))">
                <div class="chart-section">
                    <h6 style="color:#999;font-size:.9rem">Monthly Revenue</h6>
                    <h4 style="color:#484d53;margin:5px 0">$<?php echo number_format($stats['monthly_revenue'],2);?></h4>
                    <small style="color:#999">Current month</small>
                </div>
                <div class="chart-section">
                    <h6 style="color:#999;font-size:.9rem">Average Order Value</h6>
                    <h4 style="color:#484d53;margin:5px 0">$<?php echo number_format($stats['avg_order_value'],2);?></h4>
                    <small style="color:#999">Per order</small>
                </div>
                <div class="chart-section">
                    <h6 style="color:#999;font-size:.9rem">Top Category</h6>
                    <h4 style="color:#484d53;margin:5px 0"><?php echo htmlspecialchars($stats['top_category']);?></h4>
                    <small style="color:#999">Best selling</small>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="charts-grid">
                <div class="chart-section">
                    <h2><i class="fas fa-chart-line"></i> Sales Trends (Last 12 Months)</h2>
                    <canvas id="salesTrendChart"></canvas>
                </div>
                <div class="chart-section">
                    <h2><i class="fas fa-chart-pie"></i> Order Status</h2>
                    <canvas id="orderStatusChart"></canvas>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="charts-grid-equal">
                <div class="chart-section">
                    <h2><i class="fas fa-chart-bar"></i> Product Categories</h2>
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="chart-section">
                    <h2><i class="fas fa-trophy"></i> Top 10 Products</h2>
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
        </div>
    </main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const navItems=document.querySelectorAll(".nav-item");
        navItems.forEach(navItem=>{
            navItem.addEventListener("click",()=>{
                navItems.forEach(item=>item.classList.remove("active"));
                navItem.classList.add("active");
            });
        });

        document.addEventListener('DOMContentLoaded',function(){
            loadSalesTrends();
            loadCategoryDistribution();
            loadOrderStatus();
            loadTopProducts();
        });

        function loadSalesTrends(){
            fetch('analytics.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=get_sales_trends'
            })
            .then(response=>response.json())
            .then(result=>{
                if(result.success){
                    const data=result.data;
                    const ctx=document.getElementById('salesTrendChart').getContext('2d');
                    
                    new Chart(ctx,{
                        type:'line',
                        data:{
                            labels:data.map(item=>item.month),
                            datasets:[{
                                label:'Revenue ($)',
                                data:data.map(item=>parseFloat(item.revenue)),
                                borderColor:'rgb(73,57,113)',
                                backgroundColor:'rgba(73,57,113,0.1)',
                                tension:0.4,
                                fill:true
                            }]
                        },
                        options:{
                            responsive:true,
                            maintainAspectRatio:true,
                            plugins:{legend:{display:true}},
                            scales:{
                                y:{
                                    beginAtZero:true,
                                    ticks:{
                                        callback:function(value){
                                            return '$'+value.toLocaleString();
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        }

        function loadCategoryDistribution(){
            fetch('analytics.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=get_category_distribution'
            })
            .then(response=>response.json())
            .then(result=>{
                if(result.success){
                    const data=result.data;
                    const ctx=document.getElementById('categoryChart').getContext('2d');
                    
                    new Chart(ctx,{
                        type:'bar',
                        data:{
                            labels:data.map(item=>item.category),
                            datasets:[{
                                label:'Products',
                                data:data.map(item=>item.product_count),
                                backgroundColor:['rgb(73,57,113)','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#06b6d4']
                            }]
                        },
                        options:{
                            responsive:true,
                            maintainAspectRatio:true,
                            plugins:{legend:{display:false}},
                            scales:{y:{beginAtZero:true}}
                        }
                    });
                }
            });
        }

        function loadOrderStatus(){
            fetch('analytics.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=get_order_status'
            })
            .then(response=>response.json())
            .then(result=>{
                if(result.success){
                    const data=result.data;
                    const ctx=document.getElementById('orderStatusChart').getContext('2d');
                    
                    const statusColors={
                        'pending':'#f59e0b','processing':'rgb(73,57,113)','shipped':'#3b82f6',
                        'delivered':'#10b981','cancelled':'#ef4444','refunded':'#6b7280'
                    };
                    
                    new Chart(ctx,{
                        type:'doughnut',
                        data:{
                            labels:data.map(item=>item.status.charAt(0).toUpperCase()+item.status.slice(1)),
                            datasets:[{
                                data:data.map(item=>item.count),
                                backgroundColor:data.map(item=>statusColors[item.status]||'#6b7280')
                            }]
                        },
                        options:{
                            responsive:true,
                            maintainAspectRatio:true,
                            plugins:{legend:{position:'bottom'}}
                        }
                    });
                }
            });
        }

        function loadTopProducts(){
            fetch('analytics.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=get_top_products'
            })
            .then(response=>response.json())
            .then(result=>{
                if(result.success){
                    const data=result.data;
                    const ctx=document.getElementById('topProductsChart').getContext('2d');
                    
                    new Chart(ctx,{
                        type:'bar',
                        data:{
                            labels:data.map(item=>item.name.length>20?item.name.substring(0,20)+'...':item.name),
                            datasets:[{
                                label:'Units Sold',
                                data:data.map(item=>item.total_sold),
                                backgroundColor:'#10b981'
                            }]
                        },
                        options:{
                            indexAxis:'y',
                            responsive:true,
                            maintainAspectRatio:true,
                            plugins:{legend:{display:false}},
                            scales:{x:{beginAtZero:true}}
                        }
                    });
                }
            });
        }

        function exportAnalytics(){
            fetch('analytics.php',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:'action=export_analytics'
            })
            .then(response=>response.json())
            .then(result=>{
                if(result.success){
                    let csv='SALES REPORT\n\nMonth,Orders,Revenue\n';
                    result.data.sales.forEach(row=>{
                        csv+=`${row.month},${row.orders},$${row.revenue}\n`;
                    });
                    
                    csv+='\n\nCATEGORY DISTRIBUTION\n\nCategory,Products\n';
                    result.data.categories.forEach(row=>{
                        csv+=`${row.name},${row.products}\n`;
                    });
                    
                    const blob=new Blob([csv],{type:'text/csv'});
                    const url=window.URL.createObjectURL(blob);
                    const a=document.createElement('a');
                    a.href=url;
                    a.download='analytics_report_'+new Date().toISOString().split('T')[0]+'.csv';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    document.body.removeChild(a);
                    
                    alert('Analytics report exported successfully!');
                }else{
                    alert('Error exporting analytics report');
                }
            })
            .catch(error=>{
                console.error('Error:',error);
                alert('An error occurred while exporting report');
            });
        }

         function confirmLogout(e) {
    e.preventDefault();

    Swal.fire({
      title: 'Logout Confirmation',
      text: 'Are you sure you wanna log out?',
      icon: 'question',
      showCancelButton: true,
      confirmButtonColor: 'rgb(73, 57, 113)',   // your purple
      cancelButtonColor: '#aaa',
      confirmButtonText: 'Yes, log me out',
      cancelButtonText: 'Cancel',
      background: '#fefefe',
      color: '#484d53',
      backdrop: `
        rgba(73, 57, 113, 0.4)
        left top
        no-repeat
      `,
      customClass: {
        popup: 'animated fadeInDown',
        title: 'swal-title',
        confirmButton: 'swal-confirm',
        cancelButton: 'swal-cancel'
      }
    }).then((result) => {
      if (result.isConfirmed) {
        Swal.fire({
          title: 'Logging out...',
          text: 'Please wait a moment',
          icon: 'info',
          showConfirmButton: false,
          timer: 1200,
          timerProgressBar: true,
          didClose: () => {
            window.location.href = '../logout.php';
          }
        });
      }
    });
  }

        setInterval(function(){
            loadSalesTrends();
            loadCategoryDistribution();
            loadOrderStatus();
            loadTopProducts();
        },300000);
    </script>
</body>
</html>