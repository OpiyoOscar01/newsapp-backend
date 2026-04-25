{{-- resources/views/emails/standard.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        /* DefinePress Email Styles - Clean News Platform Theme */
        body {
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto,
                         'Helvetica Neue', Arial, sans-serif;
            color: #1e293b;
        }

        .email-container {
            position: relative;
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            border: 1px solid #e2e8f0;
        }

        /* Blue accent line at top */
        .email-container::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #2563eb 0%, #3b82f6 50%, #60a5fa 100%);
            z-index: 1;
        }

        /* White header with subtle blue border */
        .email-header {
            background: #ffffff;
            color: #1e293b;
            padding: 32px 24px 24px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }

        .logo-rounded {
            max-height: 56px;
            margin-bottom: 16px;
            object-fit: contain;
        }

        .brand-section {
            margin-bottom: 16px;
        }

        .brand-name {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 4px;
            line-height: 1.2;
            letter-spacing: -0.5px;
        }

        .tagline {
            font-size: 13px;
            color: #64748b;
            font-weight: 400;
            margin-bottom: 20px;
            letter-spacing: 0.3px;
        }

        .email-header h1 {
            margin: 20px 0 0;
            font-size: 20px;
            font-weight: 600;
            color: #1e293b;
            border-top: 1px solid #e2e8f0;
            padding-top: 20px;
        }

        .email-body {
            padding: 36px 28px;
            font-size: 16px;
            line-height: 1.7;
            color: #334155;
            background: #ffffff;
        }

        .email-body p {
            margin-bottom: 1.5em;
            color: #475569;
        }

        .email-body p strong {
            color: #1e293b;
        }

        /* Blue CTA Button */
        .cta-button {
            display: inline-block;
            margin: 24px 0;
            padding: 14px 36px;
            background: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            font-weight: 600;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: background-color 0.2s ease;
        }

        .cta-button:hover {
            background: #1d4ed8;
        }

        /* Info Tip Box - Light Blue */
        .email-tip {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 20px;
            margin: 24px 0;
            border-radius: 8px;
            font-size: 14px;
            color: #1e40af;
        }

        .email-tip strong {
            color: #1e3a8a;
        }

        /* Reset Code Box */
        .reset-code {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 24px;
            text-align: center;
            margin: 28px 0;
        }

        .reset-code span {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 6px;
            color: #1e293b;
            font-family: 'Courier New', monospace;
            background: #ffffff;
            padding: 12px 24px;
            border-radius: 8px;
            display: inline-block;
            border: 1px solid #e2e8f0;
        }

        /* Security Notice Box */
        .security-notice {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 16px 20px;
            margin: 24px 0;
            border-radius: 8px;
            font-size: 13px;
            color: #991b1b;
        }

        .security-notice strong {
            color: #dc2626;
        }

        /* Divider */
        .divider {
            margin: 32px 0 24px;
            border-top: 1px solid #e2e8f0;
        }

        /* Footer - White with blue text */
        .email-footer {
            background: #f8fafc;
            padding: 28px 24px;
            text-align: center;
            font-size: 13px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }

        .footer-message {
            margin-bottom: 12px;
            line-height: 1.6;
        }

        .footer-message strong {
            color: #2563eb;
            font-weight: 600;
        }

        .copyright {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 16px;
        }

        .social-links {
            margin: 16px 0 12px;
            padding: 0;
            list-style: none;
            display: flex;
            justify-content: center;
            gap: 20px;
        }

        .social-links li {
            display: inline-block;
        }

        .social-links a {
            color: #64748b;
            text-decoration: none;
            font-size: 12px;
            transition: color 0.2s ease;
        }

        .social-links a:hover {
            color: #2563eb;
        }

        @media only screen and (max-width: 620px) {
            .email-container {
                margin: 20px 12px;
                border-radius: 12px;
            }
            .email-body {
                padding: 28px 20px;
            }
            .email-header h1 {
                font-size: 18px;
            }
            .brand-name {
                font-size: 24px;
            }
            .reset-code span {
                font-size: 24px;
                letter-spacing: 4px;
                padding: 8px 16px;
            }
            .cta-button {
                padding: 12px 28px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">

        {{-- ── Header ──────────────────────────────────────────────────── --}}
        <div class="email-header">
            {{-- Logo handling --}}
            @php
                $logoToUse = $logoPath ?? public_path('images/definepress-logo.png');
                $logoExists = file_exists($logoToUse);
            @endphp

            @if($logoExists)
                <img src="{{ $message->embed($logoToUse) }}"
                     alt="{{ config('app.name', 'DefinePress') }}"
                     class="logo-rounded">
            @else
                {{-- Text fallback logo --}}
                {{-- <div style="font-size: 32px; font-weight: 800; color: #1e293b; letter-spacing: -1px; margin-bottom: 12px;">
                    DefinePress
                </div> --}}
            @endif

            <div class="brand-section">
                <div class="brand-name">DefinePress</div>
                <div class="tagline">News That Matters | Stories That Inspire</div>
            </div>

            <h1>{{ $title }}</h1>
        </div>

        {{-- ── Body ───────────────────────────────────────────────────── --}}
        <div class="email-body">

            {{-- Main content: render HTML or escape plain text --}}
            @if($isHtml)
                {!! $mailBody !!}
            @else
                <p>{!! nl2br(e($mailBody)) !!}</p>
            @endif

            {{-- Password reset code display (if provided) --}}
            @isset($resetCode)
                <div class="reset-code">
                    <div style="margin-bottom: 12px; font-size: 13px; color: #64748b; font-weight: 500;">Your verification code:</div>
                    <span>{{ $resetCode }}</span>
                    <div style="margin-top: 16px; font-size: 12px; color: #64748b;">This code expires in 60 minutes</div>
                </div>
            @endisset

            {{-- Optional pro-tip callout --}}
            @isset($tip)
                <div class="email-tip">
                    <strong>💡 Quick Tip:</strong> {{ $tip }}
                </div>
            @endisset

            {{-- Optional CTA button --}}
            @isset($ctaUrl)
                <div style="text-align: center;">
                    <a href="{{ $ctaUrl }}"
                       class="cta-button"
                       target="_blank"
                       rel="noopener noreferrer">
                        {{ $ctaLabel ?? 'Learn More' }}
                    </a>
                </div>
            @endisset

            {{-- Security notice for password reset emails --}}
            @isset($isPasswordReset)
                @if($isPasswordReset)
                    <div class="security-notice">
                        <strong>🔒 Security Notice:</strong><br>
                        If you didn't request this password reset, please ignore this email. 
                        Your password will remain unchanged. For security concerns, contact our support team.
                    </div>
                @endif
            @endisset

        </div>

        {{-- ── Footer ─────────────────────────────────────────────────── --}}
        <div class="email-footer">
            <div class="footer-message">
                <strong>DefinePress</strong> — Delivering trusted news and insights to your inbox.
            </div>
            
            <div class="copyright">
                &copy; {{ now()->year }} DefinePress. All rights reserved.<br>
                <span style="font-size: 10px;">Stay informed. Stay inspired.</span>
            </div>
            
            <div style="margin-top: 16px; font-size: 10px; color: #94a3b8;">
                You're receiving this email because you're part of the DefinePress community.
                <br>Forward this email to a friend who loves quality news.
            </div>
        </div>

    </div>
</body>
</html>