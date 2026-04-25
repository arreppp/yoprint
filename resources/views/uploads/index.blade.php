<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>YoPrint - CSV Upload System</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f0f2f5; color: #333; min-height: 100vh; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 40px; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .header h1 { font-size: 1.6rem; font-weight: 700; }
        .header p { font-size: 0.85rem; opacity: 0.85; margin-top: 2px; }
        .badge { background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 30px 20px; }
        .card { background: white; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-bottom: 24px; overflow: hidden; }
        .card-header { padding: 20px 24px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; }
        .card-header h2 { font-size: 1.1rem; font-weight: 600; color: #444; }
        .card-body { padding: 24px; }
        .drop-zone { border: 2px dashed #c8d6e5; border-radius: 10px; padding: 50px 20px; text-align: center; cursor: pointer; transition: all 0.3s ease; background: #fafbfc; position: relative; }
        .drop-zone:hover, .drop-zone.drag-over { border-color: #667eea; background: #f0f2ff; }
        .drop-zone.has-file { border-color: #52c41a; background: #f6ffed; }
        .drop-zone-icon { font-size: 3rem; margin-bottom: 12px; }
        .drop-zone-text { font-size: 1rem; color: #666; margin-bottom: 6px; }
        .drop-zone-hint { font-size: 0.8rem; color: #999; }
        .drop-zone input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
        .file-selected { display: none; align-items: center; gap: 10px; background: #f6ffed; border: 1px solid #b7eb8f; border-radius: 8px; padding: 10px 14px; margin-top: 12px; }
        .file-selected.show { display: flex; }
        .file-icon { font-size: 1.4rem; }
        .file-info { flex: 1; }
        .file-name { font-weight: 600; font-size: 0.9rem; color: #333; }
        .file-size { font-size: 0.75rem; color: #666; }
        .remove-file { background: none; border: none; cursor: pointer; font-size: 1.2rem; color: #999; padding: 0 4px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: #555; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 10px 14px; border: 1px solid #d9d9d9; border-radius: 8px; font-size: 0.9rem; transition: border-color 0.2s; }
        .form-group input:focus { outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .btn { padding: 10px 20px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; border: none; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .btn-primary:hover:not(:disabled) { opacity: 0.9; transform: translateY(-1px); }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-danger { background: #ff4d4f; color: white; }
        .btn-danger:hover:not(:disabled) { background: #ff1f1f; }
        .btn-warning { background: #faad14; color: white; }
        .btn-warning:hover:not(:disabled) { background: #d48806; }
        .btn-secondary { background: #f0f0f0; color: #555; }
        .btn-secondary:hover { background: #e0e0e0; }
        .btn-sm { padding: 5px 12px; font-size: 0.8rem; }
        .btn-link { background: none; border: none; color: #667eea; cursor: pointer; font-size: 0.85rem; padding: 0; }
        .btn-link:hover { text-decoration: underline; }
        .controls { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; }
        .search-input { flex: 1; min-width: 200px; padding: 8px 14px; border: 1px solid #d9d9d9; border-radius: 8px; font-size: 0.9rem; }
        .search-input:focus { outline: none; border-color: #667eea; }
        select.filter-select { padding: 8px 12px; border: 1px solid #d9d9d9; border-radius: 8px; font-size: 0.85rem; background: white; cursor: pointer; }
        .bulk-actions { display: none; gap: 8px; align-items: center; padding: 10px 14px; background: #e8f4fd; border-radius: 8px; }
        .bulk-actions.show { display: flex; }
        .bulk-count { font-size: 0.85rem; font-weight: 600; color: #1890ff; }
        .table-wrapper { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        thead th { background: #fafafa; padding: 12px 16px; text-align: left; font-weight: 600; color: #666; border-bottom: 2px solid #f0f0f0; white-space: nowrap; cursor: pointer; user-select: none; }
        thead th:hover { background: #f0f0f0; }
        thead th.sorted { color: #667eea; }
        tbody tr { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
        tbody tr:hover { background: #fafafa; }
        tbody td { padding: 12px 16px; vertical-align: middle; }
        .status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 3px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
        .status-pending { background: #fff7e6; color: #d48806; }
        .status-processing { background: #e6f7ff; color: #1890ff; }
        .status-completed { background: #f6ffed; color: #52c41a; }
        .status-failed { background: #fff2f0; color: #ff4d4f; }
        .progress-bar { height: 6px; background: #f0f0f0; border-radius: 3px; overflow: hidden; min-width: 80px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); border-radius: 3px; transition: width 0.5s ease; }
        .actions { display: flex; gap: 6px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-icon { font-size: 3rem; margin-bottom: 12px; }
        .pagination { display: flex; align-items: center; gap: 6px; justify-content: center; padding: 16px; flex-wrap: wrap; }
        .page-btn { width: 34px; height: 34px; border: 1px solid #d9d9d9; background: white; border-radius: 6px; cursor: pointer; font-size: 0.85rem; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .page-btn:hover:not(:disabled):not(.active) { border-color: #667eea; color: #667eea; }
        .page-btn.active { background: #667eea; color: white; border-color: #667eea; }
        .page-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .page-info { font-size: 0.8rem; color: #999; padding: 0 8px; }
        .loading { display: flex; align-items: center; justify-content: center; padding: 40px; }
        .spinner { width: 28px; height: 28px; border: 3px solid #f0f0f0; border-top-color: #667eea; border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast { padding: 12px 18px; border-radius: 8px; color: white; font-size: 0.875rem; font-weight: 500; min-width: 250px; max-width: 380px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); animation: slideIn 0.3s ease; display: flex; align-items: center; gap: 8px; }
        .toast-success { background: #52c41a; }
        .toast-error { background: #ff4d4f; }
        .toast-info { background: #1890ff; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.show { display: flex; }
        .modal { background: white; border-radius: 12px; padding: 24px; max-width: 800px; width: 90%; max-height: 80vh; display: flex; flex-direction: column; }
        .modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .modal-header h3 { font-size: 1.1rem; font-weight: 600; }
        .modal-body { overflow-y: auto; flex: 1; }
        .modal-footer { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; padding-top: 16px; border-top: 1px solid #f0f0f0; }
        .filename-cell { max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .error-tip { font-size: 0.75rem; color: #ff4d4f; margin-top: 4px; }
        .upload-progress { margin-top: 12px; display: none; }
        .upload-progress.show { display: block; }
        .upload-progress-bar { height: 4px; background: #f0f0f0; border-radius: 2px; overflow: hidden; }
        .upload-progress-fill { height: 100%; background: linear-gradient(90deg, #667eea, #764ba2); width: 0%; transition: width 0.3s; animation: pulse 1.5s ease-in-out infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.6; } }
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 10px; padding: 16px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); text-align: center; }
        .stat-number { font-size: 1.8rem; font-weight: 700; }
        .stat-label { font-size: 0.8rem; color: #888; margin-top: 2px; }
        .text-pending { color: #d48806; }
        .text-processing { color: #1890ff; }
        .text-completed { color: #52c41a; }
        .text-failed { color: #ff4d4f; }
        @media (max-width: 768px) {
            .header { padding: 16px 20px; }
            .container { padding: 16px; }
            .controls { flex-direction: column; align-items: stretch; }
            .search-input { min-width: unset; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>🖨️ YoPrint</h1>
            <p>CSV Upload & Processing System</p>
        </div>
        <span class="badge" id="ws-status">● Connecting...</span>
    </div>

    <div class="container">
        <!-- Stats Row -->
        <div class="stats-row" id="stats-row">
            <div class="stat-card"><div class="stat-number" id="stat-total">—</div><div class="stat-label">Total Uploads</div></div>
            <div class="stat-card"><div class="stat-number text-processing" id="stat-processing">—</div><div class="stat-label">Processing</div></div>
            <div class="stat-card"><div class="stat-number text-completed" id="stat-completed">—</div><div class="stat-label">Completed</div></div>
            <div class="stat-card"><div class="stat-number text-failed" id="stat-failed">—</div><div class="stat-label">Failed</div></div>
        </div>

        <!-- Upload Card -->
        <div class="card">
            <div class="card-header">
                <h2>📤 Upload CSV File</h2>
            </div>
            <div class="card-body">
                <div class="drop-zone" id="drop-zone">
                    <input type="file" id="file-input" accept=".csv,text/csv">
                    <div class="drop-zone-icon">📂</div>
                    <div class="drop-zone-text">Drag & drop your CSV file here</div>
                    <div class="drop-zone-hint">or click to browse — CSV files only, max 10MB</div>
                </div>
                <div class="file-selected" id="file-selected">
                    <span class="file-icon">📄</span>
                    <div class="file-info">
                        <div class="file-name" id="file-name"></div>
                        <div class="file-size" id="file-size"></div>
                    </div>
                    <button class="remove-file" onclick="clearFile()">✕</button>
                </div>
                <div class="upload-progress" id="upload-progress">
                    <div class="upload-progress-bar"><div class="upload-progress-fill"></div></div>
                </div>
                <div id="validation-errors" style="margin-top: 12px;"></div>
                <div class="form-group" style="margin-top: 16px;">
                    <label for="notification-email">Notification Email (optional)</label>
                    <input type="email" id="notification-email" placeholder="email@example.com">
                </div>
                <button class="btn btn-primary" id="upload-btn" onclick="uploadFile()" disabled>
                    ⬆️ Upload File
                </button>
            </div>
        </div>

        <!-- Uploads List Card -->
        <div class="card">
            <div class="card-header">
                <h2>📋 Upload History</h2>
                <div style="display: flex; gap: 10px;">
                    <button class="btn btn-sm btn-secondary" onclick="loadUploads()">🔄 Refresh</button>
                    <button class="btn btn-sm btn-secondary" onclick="showAuditLogs()">📜 Audit Logs</button>
                </div>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="padding: 16px 20px; border-bottom: 1px solid #f0f0f0;">
                    <div class="controls">
                        <input type="text" class="search-input" id="search-input" placeholder="🔍 Search by filename..." oninput="debounceSearch()">
                        <select class="filter-select" id="status-filter" onchange="applyFilter()">
                            <option value="">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                        </select>
                        <select class="filter-select" id="sort-select" onchange="applyFilter()">
                            <option value="created_at|desc">Newest First</option>
                            <option value="created_at|asc">Oldest First</option>
                            <option value="original_filename|asc">Filename A-Z</option>
                            <option value="status|asc">Status</option>
                        </select>
                    </div>
                    <div class="bulk-actions" id="bulk-actions" style="margin-top: 10px;">
                        <span class="bulk-count" id="bulk-count">0 selected</span>
                        <button class="btn btn-sm btn-danger" onclick="bulkDelete()">🗑️ Delete Selected</button>
                        <button class="btn btn-sm btn-warning" onclick="bulkRetry()">🔄 Retry Selected</button>
                        <button class="btn-link" onclick="clearSelection()">Clear</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table id="uploads-table">
                        <thead>
                            <tr>
                                <th><input type="checkbox" id="select-all" onchange="toggleSelectAll()"></th>
                                <th>Filename</th>
                                <th>Status</th>
                                <th>Progress</th>
                                <th>Rows</th>
                                <th>Uploaded</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="uploads-tbody">
                            <tr><td colspan="7"><div class="loading"><div class="spinner"></div></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="pagination-container" style="padding: 8px;"></div>
            </div>
        </div>
    </div>

    <!-- Audit Logs Modal -->
    <div class="modal-overlay" id="audit-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>📜 Audit Logs</h3>
                <button class="btn btn-sm btn-secondary" onclick="closeAuditModal()">✕ Close</button>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
                    <select class="filter-select" id="audit-action-filter" onchange="loadAuditLogs()">
                        <option value="">All Actions</option>
                        <option value="upload">Upload</option>
                        <option value="process">Process</option>
                        <option value="delete">Delete</option>
                        <option value="retry">Retry</option>
                        <option value="download">Download</option>
                        <option value="export">Export</option>
                    </select>
                    <select class="filter-select" id="audit-status-filter" onchange="loadAuditLogs()">
                        <option value="">All Statuses</option>
                        <option value="success">Success</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Action</th>
                                <th>Status</th>
                                <th>File</th>
                                <th>Details</th>
                            </tr>
                        </thead>
                        <tbody id="audit-tbody">
                            <tr><td colspan="5"><div class="loading"><div class="spinner"></div></div></td></tr>
                        </tbody>
                    </table>
                </div>
                <div id="audit-pagination" style="padding: 8px;"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="exportAuditLogs()">⬇️ Export CSV</button>
                <button class="btn btn-secondary" onclick="closeAuditModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toast-container"></div>

    <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
    <script>
        // State
        let currentPage = 1;
        let perPage = 15;
        let sortBy = 'created_at';
        let sortOrder = 'desc';
        let searchQuery = '';
        let statusFilter = '';
        let selectedIds = new Set();
        let searchDebounceTimer = null;
        let pollingInterval = null;
        let selectedFile = null;
        let auditPage = 1;

        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

        // ==================== WebSocket Setup ====================
        function setupWebSocket() {
            try {
                const pusher = new Pusher('{{ env("REVERB_APP_KEY") }}', {
                    wsHost: '{{ env("REVERB_HOST", "localhost") }}',
                    wsPort: {{ env("REVERB_PORT", 8080) }},
                    wssPort: {{ env("REVERB_PORT", 8080) }},
                    forceTLS: false,
                    enabledTransports: ['ws', 'wss'],
                    cluster: 'mt1',
                });

                const connectionTimeout = setTimeout(() => {
                    console.warn('WebSocket connection timeout, falling back to polling');
                    setWsStatus(false);
                    startPolling();
                }, 5000);

                pusher.connection.bind('connected', () => {
                    clearTimeout(connectionTimeout);
                    setWsStatus(true);
                    console.log('WebSocket connected');
                    stopPolling();
                });

                pusher.connection.bind('error', () => {
                    setWsStatus(false);
                    startPolling();
                });

                pusher.connection.bind('disconnected', () => {
                    setWsStatus(false);
                    startPolling();
                });

                const channel = pusher.subscribe('file-uploads');
                channel.bind('status.changed', (data) => {
                    console.log('WebSocket event:', data);
                    updateRowFromEvent(data);
                    loadStats();
                });

            } catch (e) {
                console.warn('WebSocket setup failed, using polling', e);
                setWsStatus(false);
                startPolling();
            }
        }

        function setWsStatus(connected) {
            const el = document.getElementById('ws-status');
            el.textContent = connected ? '● Live' : '● Polling';
            el.style.background = connected ? 'rgba(82,196,26,0.3)' : 'rgba(250,173,20,0.3)';
        }

        function startPolling() {
            if (pollingInterval) return;
            pollingInterval = setInterval(() => {
                loadUploads(false);
            }, 3000);
        }

        function stopPolling() {
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
        }

        // ==================== File Upload ====================
        const dropZone = document.getElementById('drop-zone');
        const fileInput = document.getElementById('file-input');

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });

        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            const file = e.dataTransfer.files[0];
            if (file) handleFileSelect(file);
        });

        fileInput.addEventListener('change', () => {
            if (fileInput.files[0]) handleFileSelect(fileInput.files[0]);
        });

        function handleFileSelect(file) {
            if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv') {
                showToast('Please select a CSV file', 'error');
                return;
            }
            if (file.size > 10 * 1024 * 1024) {
                showToast('File size must not exceed 10MB', 'error');
                return;
            }
            selectedFile = file;
            dropZone.classList.add('has-file');
            document.getElementById('file-name').textContent = file.name;
            document.getElementById('file-size').textContent = formatBytes(file.size);
            document.getElementById('file-selected').classList.add('show');
            document.getElementById('upload-btn').disabled = false;
            document.getElementById('validation-errors').innerHTML = '';
        }

        function clearFile() {
            selectedFile = null;
            fileInput.value = '';
            dropZone.classList.remove('has-file');
            document.getElementById('file-selected').classList.remove('show');
            document.getElementById('upload-btn').disabled = true;
            document.getElementById('validation-errors').innerHTML = '';
        }

        async function uploadFile() {
            if (!selectedFile) return;

            const btn = document.getElementById('upload-btn');
            const progress = document.getElementById('upload-progress');
            btn.disabled = true;
            btn.textContent = '⬆️ Uploading...';
            progress.classList.add('show');

            const formData = new FormData();
            formData.append('file', selectedFile);
            const email = document.getElementById('notification-email').value;
            if (email) formData.append('notification_email', email);

            try {
                const res = await fetch('/api/uploads', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                    body: formData,
                });

                const data = await res.json();

                if (!res.ok) {
                    if (res.status === 422) {
                        const errors = data.errors;
                        if (Array.isArray(errors)) {
                            showValidationErrors(errors);
                        } else if (typeof errors === 'object') {
                            showValidationErrors(Object.values(errors).flat());
                        }
                        showToast('Validation failed. Check errors above.', 'error');
                    } else {
                        showToast(data.message || 'Upload failed', 'error');
                    }
                    return;
                }

                showToast('File uploaded successfully!', 'success');
                clearFile();
                document.getElementById('notification-email').value = '';
                loadUploads();
                loadStats();

            } catch (e) {
                showToast('Upload failed: ' + e.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '⬆️ Upload File';
                progress.classList.remove('show');
            }
        }

        function showValidationErrors(errors) {
            const container = document.getElementById('validation-errors');
            container.innerHTML = errors.map(e => `<div class="error-tip">⚠️ ${e}</div>`).join('');
        }

        // ==================== Upload List ====================
        async function loadUploads(showLoader = true) {
            if (showLoader) {
                document.getElementById('uploads-tbody').innerHTML = '<tr><td colspan="7"><div class="loading"><div class="spinner"></div></div></td></tr>';
            }

            const params = new URLSearchParams({
                page: currentPage,
                per_page: perPage,
                sort_by: sortBy,
                sort_order: sortOrder,
            });
            if (searchQuery) params.set('search', searchQuery);
            if (statusFilter) params.set('status', statusFilter);

            try {
                const res = await fetch(`/api/uploads?${params}`);
                const data = await res.json();
                renderTable(data.data);
                renderPagination(data.meta);
                updateStats(data);
            } catch (e) {
                document.getElementById('uploads-tbody').innerHTML = '<tr><td colspan="7" style="text-align:center;color:#ff4d4f;padding:40px;">Failed to load data</td></tr>';
            }
        }

        async function loadStats() {
            try {
                const res = await fetch('/api/uploads?per_page=1');
                const data = await res.json();
                if (data.stats) {
                    document.getElementById('stat-total').textContent = data.stats.total ?? '—';
                    document.getElementById('stat-processing').textContent = data.stats.processing ?? '—';
                    document.getElementById('stat-completed').textContent = data.stats.completed ?? '—';
                    document.getElementById('stat-failed').textContent = data.stats.failed ?? '—';
                }
            } catch (e) {}
        }

        function updateStats(data) {
            if (data.stats) {
                document.getElementById('stat-total').textContent = data.stats.total ?? '—';
                document.getElementById('stat-processing').textContent = data.stats.processing ?? '—';
                document.getElementById('stat-completed').textContent = data.stats.completed ?? '—';
                document.getElementById('stat-failed').textContent = data.stats.failed ?? '—';
            }
        }

        function renderTable(uploads) {
            const tbody = document.getElementById('uploads-tbody');

            if (!uploads || uploads.length === 0) {
                tbody.innerHTML = `
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon">📭</div>
                            <p>No uploads found</p>
                            <p style="font-size:0.8rem;margin-top:6px;">Upload a CSV file to get started</p>
                        </div>
                    </td></tr>`;
                return;
            }

            tbody.innerHTML = uploads.map(upload => `
                <tr id="row-${upload.id}">
                    <td><input type="checkbox" class="row-checkbox" value="${upload.id}" onchange="toggleSelection(${upload.id}, this.checked)" ${selectedIds.has(upload.id) ? 'checked' : ''}></td>
                    <td>
                        <div class="filename-cell" title="${escHtml(upload.original_filename)}">${escHtml(upload.original_filename)}</div>
                        ${upload.notification_email ? `<div style="font-size:0.75rem;color:#999;">📧 ${escHtml(upload.notification_email)}</div>` : ''}
                    </td>
                    <td><span class="status-badge status-${upload.status}">${statusIcon(upload.status)} ${upload.status}</span></td>
                    <td>
                        ${upload.status === 'processing' ? `
                            <div class="progress-bar"><div class="progress-fill" style="width:${upload.progress_percentage}%"></div></div>
                            <div style="font-size:0.75rem;color:#999;margin-top:3px;">${upload.progress_percentage}%</div>
                        ` : '—'}
                    </td>
                    <td>
                        ${upload.total_rows != null ? `
                            <div style="font-size:0.85rem;">
                                <span style="color:#52c41a;">✓ ${upload.processed_rows}</span>
                                ${upload.failed_rows > 0 ? `<span style="color:#ff4d4f;"> ✗ ${upload.failed_rows}</span>` : ''}
                                <span style="color:#999;"> / ${upload.total_rows}</span>
                            </div>
                        ` : '—'}
                    </td>
                    <td style="font-size:0.8rem;color:#888;">${formatDate(upload.created_at)}</td>
                    <td>
                        <div class="actions">
                            ${upload.has_processed_file ? `<button class="btn btn-sm btn-secondary" onclick="downloadFile(${upload.id})" title="Download">⬇️</button>` : ''}
                            ${['failed', 'completed'].includes(upload.status) ? `<button class="btn btn-sm btn-warning" onclick="retryUpload(${upload.id})" title="Retry">🔄</button>` : ''}
                            <button class="btn btn-sm btn-danger" onclick="deleteUpload(${upload.id})" title="Delete">🗑️</button>
                        </div>
                        ${upload.error_message ? `<div class="error-tip" title="${escHtml(upload.error_message)}">⚠️ ${upload.error_message.substring(0, 60)}${upload.error_message.length > 60 ? '...' : ''}</div>` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function updateRowFromEvent(data) {
            const row = document.getElementById(`row-${data.id}`);
            if (!row) {
                loadUploads(false);
                return;
            }

            // Update status badge
            const badge = row.querySelector('.status-badge');
            if (badge) {
                badge.className = `status-badge status-${data.status}`;
                badge.innerHTML = `${statusIcon(data.status)} ${data.status}`;
            }

            // Update progress
            const progressCell = row.cells[3];
            if (data.status === 'processing') {
                progressCell.innerHTML = `
                    <div class="progress-bar"><div class="progress-fill" style="width:${data.progress_percentage}%"></div></div>
                    <div style="font-size:0.75rem;color:#999;margin-top:3px;">${data.progress_percentage}%</div>`;
            } else {
                progressCell.innerHTML = '—';
            }

            // Update rows count
            if (data.total_rows != null) {
                const rowsCell = row.cells[4];
                rowsCell.innerHTML = `
                    <div style="font-size:0.85rem;">
                        <span style="color:#52c41a;">✓ ${data.processed_rows}</span>
                        ${data.failed_rows > 0 ? `<span style="color:#ff4d4f;"> ✗ ${data.failed_rows}</span>` : ''}
                        <span style="color:#999;"> / ${data.total_rows}</span>
                    </div>`;
            }

            if (['completed', 'failed'].includes(data.status)) {
                loadUploads(false);
            }
        }

        function renderPagination(meta) {
            if (!meta || meta.last_page <= 1) {
                document.getElementById('pagination-container').innerHTML = '';
                return;
            }

            let html = '<div class="pagination">';
            html += `<button class="page-btn" onclick="changePage(1)" ${meta.current_page === 1 ? 'disabled' : ''}>«</button>`;
            html += `<button class="page-btn" onclick="changePage(${meta.current_page - 1})" ${meta.current_page === 1 ? 'disabled' : ''}>‹</button>`;

            const start = Math.max(1, meta.current_page - 2);
            const end = Math.min(meta.last_page, meta.current_page + 2);
            for (let i = start; i <= end; i++) {
                html += `<button class="page-btn ${i === meta.current_page ? 'active' : ''}" onclick="changePage(${i})">${i}</button>`;
            }

            html += `<button class="page-btn" onclick="changePage(${meta.current_page + 1})" ${meta.current_page === meta.last_page ? 'disabled' : ''}>›</button>`;
            html += `<button class="page-btn" onclick="changePage(${meta.last_page})" ${meta.current_page === meta.last_page ? 'disabled' : ''}>»</button>`;
            html += `<span class="page-info">Page ${meta.current_page} of ${meta.last_page} (${meta.total} total)</span>`;
            html += '</div>';

            document.getElementById('pagination-container').innerHTML = html;
        }

        function changePage(page) {
            currentPage = page;
            loadUploads();
        }

        function debounceSearch() {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => {
                searchQuery = document.getElementById('search-input').value;
                currentPage = 1;
                loadUploads();
            }, 500);
        }

        function applyFilter() {
            statusFilter = document.getElementById('status-filter').value;
            const sort = document.getElementById('sort-select').value.split('|');
            sortBy = sort[0];
            sortOrder = sort[1];
            currentPage = 1;
            loadUploads();
        }

        // ==================== Actions ====================
        async function downloadFile(id) {
            try {
                const res = await fetch(`/api/uploads/${id}/download`);
                if (!res.ok) { showToast('Download failed', 'error'); return; }
                const blob = await res.blob();
                const disposition = res.headers.get('Content-Disposition');
                const match = disposition && disposition.match(/filename="?([^"]+)"?/);
                const filename = match ? match[1] : 'processed.csv';
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url; a.download = filename; a.click();
                URL.revokeObjectURL(url);
            } catch (e) { showToast('Download error: ' + e.message, 'error'); }
        }

        async function retryUpload(id) {
            if (!confirm('Retry this upload?')) return;
            try {
                const res = await fetch(`/api/uploads/${id}/retry`, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                });
                const data = await res.json();
                if (res.ok) { showToast('Upload queued for retry', 'success'); loadUploads(); }
                else showToast(data.message || 'Retry failed', 'error');
            } catch (e) { showToast('Error: ' + e.message, 'error'); }
        }

        async function deleteUpload(id) {
            if (!confirm('Delete this upload?')) return;
            try {
                const res = await fetch('/api/uploads/bulk/delete', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: [id] }),
                });
                const data = await res.json();
                if (res.ok) { showToast(data.message, 'success'); loadUploads(); loadStats(); }
                else showToast(data.message || 'Delete failed', 'error');
            } catch (e) { showToast('Error: ' + e.message, 'error'); }
        }

        // ==================== Selection ====================
        function toggleSelection(id, checked) {
            if (checked) selectedIds.add(id); else selectedIds.delete(id);
            updateBulkActions();
        }

        function toggleSelectAll() {
            const checked = document.getElementById('select-all').checked;
            document.querySelectorAll('.row-checkbox').forEach(cb => {
                cb.checked = checked;
                const id = parseInt(cb.value);
                if (checked) selectedIds.add(id); else selectedIds.delete(id);
            });
            updateBulkActions();
        }

        function clearSelection() {
            selectedIds.clear();
            document.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('select-all').checked = false;
            updateBulkActions();
        }

        function updateBulkActions() {
            const count = selectedIds.size;
            const el = document.getElementById('bulk-actions');
            document.getElementById('bulk-count').textContent = `${count} selected`;
            el.classList.toggle('show', count > 0);
        }

        async function bulkDelete() {
            if (!selectedIds.size || !confirm(`Delete ${selectedIds.size} upload(s)?`)) return;
            try {
                const res = await fetch('/api/uploads/bulk/delete', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(selectedIds) }),
                });
                const data = await res.json();
                if (res.ok) { showToast(data.message, 'success'); clearSelection(); loadUploads(); loadStats(); }
                else showToast(data.message || 'Delete failed', 'error');
            } catch (e) { showToast('Error: ' + e.message, 'error'); }
        }

        async function bulkRetry() {
            if (!selectedIds.size || !confirm(`Retry ${selectedIds.size} upload(s)?`)) return;
            try {
                const res = await fetch('/api/uploads/bulk/retry', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: Array.from(selectedIds) }),
                });
                const data = await res.json();
                if (res.ok) { showToast(data.message, 'success'); clearSelection(); loadUploads(); }
                else showToast(data.message || 'Retry failed', 'error');
            } catch (e) { showToast('Error: ' + e.message, 'error'); }
        }

        // ==================== Audit Logs ====================
        function showAuditLogs() {
            document.getElementById('audit-modal').classList.add('show');
            loadAuditLogs();
        }

        function closeAuditModal() {
            document.getElementById('audit-modal').classList.remove('show');
        }

        async function loadAuditLogs(page = 1) {
            auditPage = page;
            const action = document.getElementById('audit-action-filter').value;
            const status = document.getElementById('audit-status-filter').value;
            const params = new URLSearchParams({ page, per_page: 20 });
            if (action) params.set('action', action);
            if (status) params.set('status', status);

            document.getElementById('audit-tbody').innerHTML = '<tr><td colspan="5"><div class="loading"><div class="spinner"></div></div></td></tr>';

            try {
                const res = await fetch(`/api/audit-logs?${params}`);
                const data = await res.json();
                const tbody = document.getElementById('audit-tbody');

                if (!data.data || data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:30px;color:#999;">No audit logs found</td></tr>';
                    return;
                }

                tbody.innerHTML = data.data.map(log => `
                    <tr>
                        <td style="font-size:0.78rem;color:#888;">${formatDate(log.created_at)}</td>
                        <td><span class="status-badge" style="background:#f0f0f0;color:#555;">${log.action}</span></td>
                        <td><span class="status-badge ${log.status === 'success' ? 'status-completed' : 'status-failed'}">${log.status}</span></td>
                        <td style="font-size:0.8rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escHtml(log.original_filename || '')}">${escHtml(log.original_filename || 'N/A')}</td>
                        <td style="font-size:0.8rem;max-width:200px;">${escHtml(log.details || '')}</td>
                    </tr>
                `).join('');

                renderAuditPagination(data.meta);
            } catch (e) {
                document.getElementById('audit-tbody').innerHTML = '<tr><td colspan="5" style="text-align:center;color:#ff4d4f;padding:30px;">Failed to load logs</td></tr>';
            }
        }

        function renderAuditPagination(meta) {
            if (!meta || meta.last_page <= 1) {
                document.getElementById('audit-pagination').innerHTML = '';
                return;
            }
            let html = '<div class="pagination">';
            for (let i = 1; i <= meta.last_page; i++) {
                html += `<button class="page-btn ${i === meta.current_page ? 'active' : ''}" onclick="loadAuditLogs(${i})">${i}</button>`;
            }
            html += `<span class="page-info">${meta.total} total</span></div>`;
            document.getElementById('audit-pagination').innerHTML = html;
        }

        async function exportAuditLogs() {
            const action = document.getElementById('audit-action-filter').value;
            const status = document.getElementById('audit-status-filter').value;
            const params = new URLSearchParams();
            if (action) params.set('action', action);
            if (status) params.set('status', status);
            window.location.href = `/api/audit-logs/export?${params}`;
        }

        // ==================== Utilities ====================
        function showToast(message, type = 'info') {
            const container = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
            toast.innerHTML = `${icon} ${escHtml(message)}`;
            container.appendChild(toast);
            setTimeout(() => { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.3s'; setTimeout(() => toast.remove(), 300); }, 4000);
        }

        function escHtml(str) {
            if (!str) return '';
            return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function formatBytes(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        }

        function formatDate(dateStr) {
            if (!dateStr) return '—';
            const d = new Date(dateStr);
            return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function statusIcon(status) {
            const icons = { pending: '⏳', processing: '⚙️', completed: '✅', failed: '❌' };
            return icons[status] || '?';
        }

        // ==================== Init ====================
        document.addEventListener('DOMContentLoaded', () => {
            loadUploads();
            setupWebSocket();
        });

        // Close modal on overlay click
        document.getElementById('audit-modal').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) closeAuditModal();
        });
    </script>
</body>
</html>
