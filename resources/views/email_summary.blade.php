@php
  $border_color = '#EAEAEA';
  $bg_color     = '#F2F8FC';
  $text_color   = '#154273';
  $text_size    = '12px';
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>DonlSync daily summary</title>
</head>
<body style="margin: 0; padding: 0; background-color: {{ $bg_color }}; color: {{ $text_color }}; font-family: Tahoma, sans-serif; font-size: 16px; line-height: 20px;">
<table border="0" cellpadding="0" cellspacing="0" width="100%">
  <tr>
    <td style="padding: 10px 0 30px 0;">
      <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #FFFFFF; border: 1px solid {{ $border_color }}; border-collapse: collapse;">
        <tr>
          <td style="padding: 25px; text-align: center;">
            <img src="cid:overheid.nl.png" width="32" height="32" alt="Overheid.nl logo">
          </td>
        </tr>
        <tr>
          <td style="padding: 25px;">
            <h1 style="margin:0; padding:0; font-size: 18px;"><font color="{{ $text_color }}">
                DonlSync daily summary ({{ $date }})</font>
            </h1>
          </td>
        </tr>
        <tr>
          <td style="padding: 5px 25px;">
            <p style="font-size: {{ $text_size }}; margin-bottom: 25px;">
              <font color="{{ $text_color }}">The daily execution of DonlSync targeting the {{ $target_name }} (<a href="{{ $target_url }}">{{ $target_url }}</a>) environment has concluded.</font>
            </p>
            <table border="0" cellpadding="5" cellspacing="0" style="font-size: {{ $text_size }};">
              <tr>
                <th style="width: 100px; text-align: left; border-bottom: 1px solid {{ $text_color }};"><font color="{{ $text_color }}">Summary</font></th>
                @foreach($summary_keys as $key)
                  <th style="width: 100px; text-align: right; border-bottom: 1px solid {{ $text_color }};">
                    <font color="{{ $text_color }}">{{ $key }}</font>
                  </th>
                @endforeach
              </tr>
              <tr>
                <td><font color="{{ $text_color }}">Validated</font></td>
                @foreach($summary_keys as $key)
                  <td style="text-align: right; font-weight: bold;">
                    <font color="{{ $text_color }}">{{ $summary[$key]['validated_datasets'] }}</font>
                  </td>
                @endforeach
              </tr>
              <tr>
                <td><font color="{{ $text_color }}"> &nbsp;&raquo; Created</font></td>
                @foreach($summary_keys as $key)
                  <td style="text-align: right;">
                    <font color="{{ $text_color }}">{{ $summary[$key]['created_datasets'] }}</font>
                  </td>
                @endforeach
              </tr>
              <tr>
                <td><font color="{{ $text_color }}"> &nbsp;&raquo; Updated</font></td>
                @foreach($summary_keys as $key)
                  <td style="text-align: right;">
                    <font color="{{ $text_color }}">{{ $summary[$key]['updated_datasets'] }}</font>
                  </td>
                @endforeach
              </tr>
              <tr>
                <td><font color="{{ $text_color }}"> &nbsp;&raquo; Ignored</font></td>
                @foreach($summary_keys as $key)
                  <td style="text-align: right;">
                    <font color="{{ $text_color }}">{{ $summary[$key]['ignored_datasets'] }}</font>
                  </td>
                @endforeach
              </tr>
              <tr>
                <td><font color="{{ $text_color }}"> &nbsp;&raquo; Rejected</font></td>
                @foreach($summary_keys as $key)
                  <td style="text-align: right;">
                    <font color="{{ $text_color }}">{{ $summary[$key]['rejected_datasets'] }}</font>
                  </td>
                @endforeach
              </tr>
              <tr>
                <td style="border-top: 1px solid {{ $border_color }};"><font color="{{ $text_color }}">Discarded</font></td>
                @foreach($summary_keys as $key)
                  <td style="border-top: 1px solid {{ $border_color }}; text-align: right; font-weight: bold;">
                    <font color="{{ $text_color }}">{{ $summary[$key]['discarded_datasets'] }}</font>
                  </td>
                @endforeach
              </tr>
              <tr>
                <td style="border-top: 1px solid {{ $border_color }};"><font color="{{ $text_color }}">Deleted</font></td>
                @foreach($summary_keys as $key)
                  <td style="border-top: 1px solid {{ $border_color }}; text-align: right; font-weight: bold;">
                    <font color="{{ $text_color }}">{{ $summary[$key]['deleted_datasets'] }}</font>
                  </td>
                @endforeach
              </tr>
              <tr>
                <td style="border-top: 1px solid {{ $border_color }};"><font color="{{ $text_color }}">Conflicts</font></td>
                @foreach($summary_keys as $key)
                  <td style="border-top: 1px solid {{ $border_color }}; text-align: right; font-weight: bold;">
                    <font color="{{ $text_color }}">{{ $summary[$key]['conflict_datasets'] }}</font>
                  </td>
                @endforeach
              </tr>
            </table>
            <p>&nbsp;</p>
            @if(!empty($alerts))
              <table border="0" cellpadding="5" cellspacing="0" style="font-size: {{ $text_size }};">
                <tr>
                  <th style="text-align: left; border-bottom: 1px solid {{ $text_color }};"><font color="{{ $text_color }}">Alerts</font></th>
                </tr>
                @foreach($alerts as $alert)
                  <tr>
                    <td><font color="#d52b1e">{{ $alert['message'] }}</font></td>
                  </tr>
                @endforeach
              </table>
            @endif
            <p style="margin-top: 25px; font-size: {{ $text_size }};">
              <font color="{{ $text_color }}">The complete synchronization logs per catalog are found inside the <strong>.zip</strong> attachment of this message.</font>
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding: 5px 25px;">
            <p style="font-size: {{ $text_size }}; font-style: italic;">
              <font color="{{ $text_color }}">
                You are receiving this message because you are configured to receive status updates regarding the DonlSync imports for the '{{ $environment }}' environment.
                If you wish to remove yourself as a recipient of these messages, please contact <a href="mailto:{{ $email_source }}">{{ $email_source }}</a>.
              </font>
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding: 25px;">
            <p style="padding-top: 10px; font-size: 10px; border-top: 1px solid {{ $text_color }};">
              <font color="{{ $text_color }}">
                DonlSync version: <strong>{{ $version }}</strong> |
                Environment: <strong>{{ $environment }}</strong>
              </font>
            </p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
