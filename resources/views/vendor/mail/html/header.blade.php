@props(['url'])
<tr><td class="header" align="center" style="padding:24px 12px 0;text-align:center;">
<table class="email-header" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation"><tr><td style="padding:28px 20px;background-color:#102e3a;text-align:center;border-radius:12px 12px 0 0;">
<a href="{{ preg_replace('/^http:/i', 'https:', rtrim(config('app.url'), '/')) }}" style="display:inline-block;color:#ffffff;text-decoration:none;">
<img src="{{ preg_replace('/^http:/i', 'https:', rtrim(config('app.url'), '/')).'/images/hoa-email-logo.png' }}" width="80" height="80" alt="Southville Phase I HOA logo" style="display:block;width:80px;height:80px;margin:0 auto 12px;border:0;background-color:#ffffff;border-radius:50%;">
<span style="font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:bold;color:#ffffff;">Southville Phase I HOA</span>
</a></td></tr></table></td></tr>
