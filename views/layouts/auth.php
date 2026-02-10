<?php use App\Core\Helpers; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?? 'Secureon.cloud' ?></title>
  <link rel="icon" type="image/png" href="<?= Helpers::logoUrl() ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            ink: '#0b1320',
            brand: '#0b74de',
            brandDark: '#0a5fb5',
            accent: '#ff8a00',
            sky: '#eaf4ff',
            mist: '#f6f9ff',
          }
        },
        fontFamily: {
          display: ['Space Grotesk', 'ui-sans-serif', 'system-ui'],
          body: ['Manrope', 'ui-sans-serif', 'system-ui'],
        }
      }
    }
  </script>
</head>
<body class="bg-mist text-ink font-body">
  <div class="fixed top-6 left-6 z-10">
    <a href="<?= Helpers::baseUrl('/') ?>" class="inline-flex items-center">
      <img src="<?= Helpers::logoUrl() ?>" alt="Secureon.cloud logo" class="h-28 w-28 object-contain rounded">
    </a>
  </div>
  <?= $content ?>
</body>
</html>
