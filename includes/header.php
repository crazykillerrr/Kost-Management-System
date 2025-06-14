<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Sistem Kost'; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --pink-primary: #e91e63;
            --pink-secondary: #f8bbd9;
            --pink-light: #fce4ec;
            --pink-dark: #ad1457;
        }
        
        body {
            background: linear-gradient(135deg, var(--pink-light) 0%, #ffffff 100%);
            min-height: 100vh;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--pink-primary) 0%, var(--pink-dark) 100%);
            box-shadow: 0 2px 10px rgba(233, 30, 99, 0.3);
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        
        .btn-pink {
            background: linear-gradient(135deg, var(--pink-primary) 0%, var(--pink-dark) 100%);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-pink:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(233, 30, 99, 0.4);
            color: white;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--pink-secondary) 0%, var(--pink-light) 100%);
            border-radius: 15px 15px 0 0 !important;
            border-bottom: 2px solid var(--pink-primary);
        }
        
        .form-control:focus {
            border-color: var(--pink-primary);
            box-shadow: 0 0 0 0.2rem rgba(233, 30, 99, 0.25);
        }
        
        .table-pink thead {
            background: linear-gradient(135deg, var(--pink-primary) 0%, var(--pink-dark) 100%);
            color: white;
        }
        
        .badge-pink {
            background-color: var(--pink-primary);
        }
        
        .alert-pink {
            background-color: var(--pink-light);
            border-color: var(--pink-secondary);
            color: var(--pink-dark);
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--pink-primary) 0%, var(--pink-dark) 100%);
            min-height: 100vh;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            border-radius: 10px;
            margin: 5px 0;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .stats-card {
            background: linear-gradient(135deg, #ffffff 0%, var(--pink-light) 100%);
            border-left: 4px solid var(--pink-primary);
        }
    </style>
</head>
<body>
