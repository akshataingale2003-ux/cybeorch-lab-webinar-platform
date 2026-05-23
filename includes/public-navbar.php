<?php
/**
 * Shared public site navbar.
 * Set $navActive before include: home | about | services | solutions | freelancers | support-desk | contact | bootcamps | webinars | live-projects | products | assignments | ''
 */
$navActive = $navActive ?? '';
$isActive = static fn(string $key): string => $navActive === $key ? ' active' : '';
$trainingOpen = in_array($navActive, ['bootcamps', 'webinars', 'training'], true);
$projectsOpen = in_array($navActive, ['live-projects', 'products', 'projects', 'assignments'], true);

startSession();
$navUserLoggedIn = isLoggedIn();
$navUserInitial = 'U';
if ($navUserLoggedIn && !empty($_SESSION['user_name'])) {
    $navUserInitial = strtoupper(substr((string) $_SESSION['user_name'], 0, 1));
}
?>
<div class="site-header-sticky">
<?php
if (function_exists('renderPublicDbAlert')) {
    renderPublicDbAlert();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark">
  <div class="container">
    <a class="navbar-brand" href="<?= url('index.php') ?>" aria-label="CYBEORCH home">
      <img src="<?= url('assets/images/logo.jpeg') ?>" alt="CYBEORCH" class="navbar-logo-img" width="160" height="42">
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu" aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon-custom"><i class="fas fa-bars"></i></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto me-lg-3 align-items-lg-center">
        <li class="nav-item">
          <a class="nav-link<?= $isActive('home') ?>" href="<?= url('index.php') ?>">Home</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isActive('about') ?>" href="<?= url('about.php') ?>">About Us</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isActive('services') ?>" href="<?= url('services.php') ?>">Services</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isActive('solutions') ?>" href="<?= url('solutions.php') ?>">Solutions</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle<?= $trainingOpen ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Training</a>
          <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item<?= $isActive('bootcamps') ?>" href="<?= url('bootcamps.php') ?>">Bootcamps</a></li>
            <li><a class="dropdown-item<?= $isActive('webinars') ?>" href="<?= url('webinars.php') ?>">Webinars</a></li>
          </ul>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle<?= $projectsOpen ? ' active' : '' ?>" href="#" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">Projects</a>
          <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item<?= $isActive('assignments') ?>" href="<?= url('assignments.php') ?>">Assignments</a></li>
            <li><a class="dropdown-item<?= $isActive('live-projects') ?>" href="<?= url('live-projects.php') ?>">Live Projects</a></li>
            <li><a class="dropdown-item<?= $isActive('products') ?>" href="<?= url('products.php') ?>">Products</a></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isActive('freelancers') ?>" href="<?= url('freelancer.php') ?>">Freelancers</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isActive('support-desk') ?>" href="<?= url('supportdesk.php') ?>">Support Desk</a>
        </li>
        <li class="nav-item">
          <a class="nav-link<?= $isActive('contact') ?>" href="<?= url('contact.php') ?>">Contact</a>
        </li>
      </ul>
      <div class="d-flex align-items-center gap-2 py-0">
        <?php if ($navUserLoggedIn): ?>
        <div class="dropdown nav-profile-dropdown">
          <button class="nav-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Account menu">
            <span class="nav-profile-avatar" aria-hidden="true"><?= htmlspecialchars($navUserInitial) ?></span>
          </button>
          <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
            <li class="dropdown-header text-truncate" style="max-width:220px"><?= htmlspecialchars($_SESSION['user_name'] ?? 'Account') ?></li>
            <li><a class="dropdown-item" href="<?= url('profile.php') ?>"><i class="fa-solid fa-user me-2"></i>My Profile</a></li>
            <li><a class="dropdown-item" href="<?= url('dashboard.php') ?>"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a></li>
            <li><a class="dropdown-item" href="<?= url('wallet.php') ?>"><i class="fa-solid fa-wallet me-2"></i>Wallet</a></li>
            <li><hr class="dropdown-divider border-secondary"></li>
            <li><a class="dropdown-item" href="<?= url('logout.php') ?>"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
          </ul>
        </div>
        <?php else: ?>
        <a href="<?= url('login.php') ?>" class="btn-nav-login">Sign In</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</nav>
</div>

