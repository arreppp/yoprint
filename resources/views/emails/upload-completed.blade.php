<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f5f5f5; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 1.5rem; margin: 0; }
        .header p { opacity: 0.85; margin-top: 6px; font-size: 0.9rem; }
        .body { padding: 30px; }
        .status-banner { padding: 16px 20px; border-radius: 8px; margin-bottom: 24px; font-weight: 600; font-size: 1.1rem; }
        .success { background: #f6ffed; color: #52c41a; border: 1px solid #b7eb8f; }
        .failed { background: #fff2f0; color: #ff4d4f; border: 1px solid #ffccc7; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 24px; }
        .stat-item { background: #fafafa; border-radius: 8px; padding: 14px; }
        .stat-label { font-size: 0.75rem; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 1.2rem; font-weight: 700; color: #333; margin-top: 4px; }
        .error-box { background: #fff2f0; border: 1px solid #ffccc7; border-radius: 8px; padding: 16px; margin-top: 16px; }
        .error-box h3 { color: #ff4d4f; margin: 0 0 8px; font-size: 0.9rem; }
        .error-box p { color: #666; font-size: 0.85rem; margin: 0; }
        .footer { background: #f5f5f5; padding: 20px 30px; text-align: center; font-size: 0.8rem; color: #888; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🖨️ YoPrint</h1>
            <p>CSV Upload System - Notification</p>
        </div>
        <div class="body">
            <div class="status-banner {{ $success ? 'success' : 'failed' }}">
                {{ $success ? '✅ Upload Completed Successfully' : '❌ Upload Failed' }}
            </div>

            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-label">Filename</div>
                    <div class="stat-value" style="font-size:0.95rem;">{{ $fileUpload->original_filename }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Status</div>
                    <div class="stat-value">{{ ucfirst($fileUpload->status) }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Total Rows</div>
                    <div class="stat-value">{{ number_format($fileUpload->total_rows ?? 0) }}</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Processed</div>
                    <div class="stat-value" style="color:#52c41a;">{{ number_format($fileUpload->processed_rows) }}</div>
                </div>
                @if($fileUpload->failed_rows > 0)
                <div class="stat-item">
                    <div class="stat-label">Failed Rows</div>
                    <div class="stat-value" style="color:#ff4d4f;">{{ number_format($fileUpload->failed_rows) }}</div>
                </div>
                @endif
                @if($fileUpload->completed_at)
                <div class="stat-item">
                    <div class="stat-label">Completed At</div>
                    <div class="stat-value" style="font-size:0.85rem;">{{ $fileUpload->completed_at->format('M d, Y H:i') }}</div>
                </div>
                @endif
            </div>

            @if(!$success && $fileUpload->error_message)
            <div class="error-box">
                <h3>Error Details</h3>
                <p>{{ $fileUpload->error_message }}</p>
            </div>
            @endif

            @if($success)
            <p style="color:#666;font-size:0.9rem;">The processed CSV file has been attached to this email.</p>
            @endif
        </div>
        <div class="footer">
            This is an automated notification from YoPrint CSV Upload System
        </div>
    </div>
</body>
</html>
