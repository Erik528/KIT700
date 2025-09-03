<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,200..1000;1,200..1000&family=Roboto:ital,wght@0,100..900;1,100..900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="./css/style.css">
    <title>Launceston Grammar School</title>
</head>

<body>
    <header class="header" id="headerTop">
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light">
                <a class="navbar-brand" href="index.php"><img src="./img/LCGS_FullColour_Primary.svg" alt="Logo"
                        class="img-fluid"></a>
                <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ml-auto ms-auto d-flex align-items-center">
                        <?php if (!empty($_SESSION['user_id'])): ?>
                            <li class="nav-item">
                                <a href="logout.php" class="btn btn-secondary btn-sm">Logout</a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a href="login.php" class="btn btn-secondary btn-sm">Sign In</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </nav>
        </div>
    </header>

    <?php
    // --- Dynamic banner (put this inside header.php where the banner should render) ---

    // Optional per-page controls (set these BEFORE including header.php):
    // $banner_title    = 'Custom Title';   // override
    // $banner_subtitle = 'Optional subtitle';
    // $show_banner     = false;            // hide banner on a specific page
    // $banner_class    = 'banner--compact';

    $show_banner  = $show_banner ?? true;
    $banner_class = $banner_class ?? '';

    if ($show_banner) {
        if (!isset($banner_title) || trim($banner_title) === '') {
            // derive from the requesting page (not header.php)
            $script   = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
            $filename = pathinfo($script, PATHINFO_FILENAME);

            // friendly names for common routes
            $friendly = [
                'index'        => 'Home',
                'manager-dash' => 'Manager Dashboard',
                'staff-dash'   => 'Staff Dashboard',
                'admin-dash'   => 'Admin Dashboard',
                'login '       => 'Login Page',
                'hr-dash'   => 'HR Dashboard'
            ];

            if (isset($friendly[$filename])) {
                $banner_title = $friendly[$filename];
            } else {
                // humanize filename: hyphens/underscores -> spaces; split camelCase; Title Case
                $title = preg_replace('/[-_]+/', ' ', $filename);
                $title = preg_replace('/(?<!^)(?=[A-Z])/', ' ', $title);
                $banner_title = ucwords(trim($title));
            }
        }

        $banner_subtitle = $banner_subtitle ?? null;
    ?>
        <div class="banner <?= htmlspecialchars($banner_class, ENT_QUOTES, 'UTF-8') ?>">
            <div class="container">
                <h1><?= htmlspecialchars($banner_title, ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if ($banner_subtitle): ?>
                    <p class="text-muted mb-0"><?= htmlspecialchars($banner_subtitle, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php
    }
    // --- /Dynamic banner ---