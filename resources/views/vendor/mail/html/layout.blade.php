<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}

/* Dark mode: clients that honour it get the yard's night colours; the gold button and links keep their contrast on either ground. */
@media (prefers-color-scheme: dark) {
body, .wrapper, .body { background-color: #111517 !important; }
.inner-body { background-color: #191f22 !important; border-color: #2b3438 !important; }
.content-cell, .content-cell p, .content-cell li, .table td { color: #d4dadd !important; }
h1, h2, h3, .content-cell strong { color: #f1f3f3 !important; }
.subcopy { border-top-color: #2b3438 !important; }
.subcopy p, .footer p, .footer a, .button-fallback, .button-fallback a { color: #8d989d !important; }
.panel-content { background-color: #20272a !important; color: #d4dadd !important; }
.panel-content p { color: #d4dadd !important; }
.header { background-color: #0b0e0f !important; }
}
</style>
{!! $head ?? '' !!}
</head>
<body>

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
