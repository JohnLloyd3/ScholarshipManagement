<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?? 'ScholarHub' ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= $base_path ?? '../' ?>assets/image/logo.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= $base_path ?? '../' ?>assets/modern-theme.css?v=20260419">
  <?php if (isset($extra_css)): ?>
    <style><?= $extra_css ?></style>
  <?php endif; ?>
  <style>
    /* Page load */
    body { opacity: 0; animation: pageLoad 0.2s ease 0.03s forwards; }
    @keyframes pageLoad { to { opacity: 1; } }

    /* Scrollbar */
    ::-webkit-scrollbar { width: 5px; height: 5px; }
    ::-webkit-scrollbar-track { background: transparent; }
    ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 99px; }
    ::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

    /* Selection */
    ::selection { background: #dbeafe; color: #1e40af; }

    /* ── Tooltip ── */
    [data-tip] { position: relative; }
    [data-tip]::after {
      content: attr(data-tip);
      position: absolute; bottom: calc(100% + 8px); left: 50%;
      transform: translateX(-50%) scale(0.92);
      background: #111827; color: #fff;
      font-size: 0.7rem; font-weight: 500; padding: 4px 10px;
      border-radius: 6px; white-space: nowrap; pointer-events: none;
      opacity: 0; transition: opacity 0.15s ease, transform 0.15s ease;
      z-index: 9999; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    [data-tip]::before {
      content: ''; position: absolute; bottom: calc(100% + 3px); left: 50%;
      transform: translateX(-50%) scale(0.92);
      border: 5px solid transparent; border-top-color: #111827;
      pointer-events: none; opacity: 0;
      transition: opacity 0.15s ease, transform 0.15s ease; z-index: 9999;
    }
    [data-tip]:hover::after, [data-tip]:hover::before {
      opacity: 1; transform: translateX(-50%) scale(1);
    }

    /* ── Button hover text ── */
    .btn-primary:hover, .btn-danger:hover,
    .btn-success:hover, .btn-warning:hover { color: #ffffff !important; }
    .btn-secondary:hover { color: #1d4ed8 !important; }
    .btn-ghost:hover { color: #111827 !important; }

    /* ── Page header — clean white with blue left accent ── */
    .page-header {
      background: #ffffff !important;
      border-radius: 12px !important;
      padding: 16px 24px !important;
      margin-bottom: 20px !important;
      box-shadow: 0 1px 4px rgba(0,0,0,0.06) !important;
      border: 1px solid #e5e7eb !important;
      display: flex !important;
      align-items: center !important;
      justify-content: space-between !important;
      flex-wrap: wrap !important;
      gap: 12px !important;
      position: relative !important;
      overflow: hidden !important;
    }
    .page-header::before {
      content: '' !important;
      position: absolute !important;
      left: 0; top: 0; bottom: 0; width: 4px !important;
      background: #2563eb !important;
      border-radius: 12px 0 0 12px !important;
      display: block !important;
    }
    .page-header h1 {
      font-size: 1.2rem !important;
      font-weight: 700 !important;
      color: #111827 !important;
      margin: 0 !important;
      padding-left: 8px !important;
    }
    .page-header p, .page-header .text-muted {
      color: #6b7280 !important;
      font-size: 0.875rem !important;
      margin: 0 !important;
    }

    /* ── Table hover ── */
    .modern-table tbody tr:hover td { color: #111827 !important; }

    /* ── Focus ring ── */
    *:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
  </style>
</head>
<body>
