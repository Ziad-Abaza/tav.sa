@php
    $settings = get_batch_settings(['theme.favicon', 'theme.seo_meta_title']);
    $faviconPath = $settings['theme.favicon'] ? Storage::url($settings['theme.favicon']) : asset('img/favicon.png');
    $seoMetaTitle = $settings['theme.seo_meta_title'] ? $settings['theme.seo_meta_title'] . ' - API V2 Documentation' : 'API V2 Documentation';
@endphp
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>{{ $seoMetaTitle }}</title>
    <link rel="stylesheet" type="text/css" href="{{ asset('swagger-assets/swagger-ui.css') }}" />
    <link rel="stylesheet" type="text/css" href="{{ asset('swagger-assets/index.css') }}" />
    <link rel="icon" type="image/png" href="{{ $faviconPath }}" sizes="32x32" />
    <link rel="icon" type="image/png" href="{{ $faviconPath }}" sizes="16x16" />
    <link rel="apple-touch-icon" href="{{ $faviconPath }}" />
     <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&family=Lexend:wght@100..900&display=swap">
    <style>
      .topbar, .url {
        display: none;
      }
      body, .swagger-ui {
        font-family: 'Inter', sans-serif !important;
      }
      .swagger-ui * {
        font-family: 'Inter', sans-serif !important;
      }
    </style>
  </head>

  <body>
    <div id="swagger-ui"></div>
    <script src="{{ asset('swagger-assets/swagger-ui-bundle.js') }}" charset="UTF-8"></script>
    <script src="{{ asset('swagger-assets/swagger-ui-standalone-preset.js') }}" charset="UTF-8"></script>
    <script src="{{ asset('swagger-assets/swagger-initializer.js') }}" charset="UTF-8"></script>
  </body>
</html>
