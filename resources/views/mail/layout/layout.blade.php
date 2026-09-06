<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=edge"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>@yield('title') | {{ config('app.name') }}</title>

    <!--[if mso]>
    <style type="text/css">
        table {
            border-collapse: collapse;
            border-spacing: 0;
            margin: 0;
        }

        div, td {
            padding: 0;
        }

        div {
            margin: 0 !important;
        }
    </style>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        img {
            border: 0;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
            display: block;
        }

        table {
            border-spacing: 0;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        td {
            padding: 0;
            mso-line-height-rule: exactly;
        }

        a {
            text-decoration: none;
        }

        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        .socus_container {
            width: 700px;
        }

        @media only screen and (max-width: 600px) {
            .socus_wrapper {
                padding: 20px 10px !important;
            }

            .socus_container {
                width: 100% !important;
            }

            .socus_header {
                padding: 25px 20px !important;
            }

            .socus_content {
                padding: 25px 20px !important;
            }

            .socus_title {
                font-size: 28px !important;
                line-height: 34px !important;
            }

            .socus_subtitle {
                font-size: 15px !important;
                line-height: 24px !important;
            }

            .socus_footer {
                padding: 30px 20px !important;
            }
        }

        @media only screen and (max-width: 480px) {
            .socus_title {
                font-size: 24px !important;
                line-height: 30px !important;
            }
        }
    </style>
</head>

<body style="margin: 0; padding: 0; background-color: #e8e8e8;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #e8e8e8;">
        <tr>
            <td align="center" class="socus_wrapper">

                <table role="presentation" cellpadding="0" cellspacing="0" border="0"
                       style="background-color: #ffffff; max-width: 700px; margin: 30px auto; border: 2px solid #ffffff; border-radius: 20px;" class="socus_container">

                    @include('mail.layout.header')

                    <tr>
                        <td style="background-color: #ffffff; padding: 25px 40px;" class="socus_content">
                            @yield('content')
                        </td>
                    </tr>

                    @include('mail.layout.footer')
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
