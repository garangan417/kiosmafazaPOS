<!-- partials/user-batch-form.php -->
<div class="card card-body shadow-sm mb-4 border-primary">
    <h5 class="card-title mb-3 text-primary">⚡ Generate Voucher Massal (Acak)</h5>
    <form hx-post="user-batch.php" 
          hx-target="#user-list-container" 
          hx-swap="innerHTML"
          hx-on::after-request="if(event.detail.successful) this.reset()">
        <div class="row g-3">
            <!-- Jumlah Voucher -->
            <div class="col-md-3">
                <label class="form-label fw-bold">Jumlah Voucher</label>
                <input type="number" name="qty" class="form-control" value="10" min="1" max="100" required>
            </div>

            <!-- Pilihan Bandwidth -->
            <div class="col-md-3">
                <label class="form-label fw-bold">Bandwidth</label>
                <select name="rate_limit" class="form-select">
                    <option value="512k/512k">512 Kbps</option>
                    <option value="1M/1M" selected>1 Mbps</option>
                    <option value="2M/2M">2 Mbps</option>
                    <option value="3M/3M">3 Mbps</option>
                </select>
            </div>

            <!-- Pilihan Masa Aktif -->
            <div class="col-md-3">
                <label class="form-label fw-bold">Durasi (Masa Aktif)</label>
                <select name="validity" class="form-select">
                    <option value="180">3 Menit (Tes)</option>
                    <option value="3600" selected>1 Jam</option>
                    <option value="86400">1 Hari (24 Jam)</option>
                    <option value="259200">3 Hari</option>
                    <option value="604800">7 Hari</option>
                </select>
            </div>

            <!-- Submit Button -->
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold">🚀 Generate Sekarang</button>
            </div>
        </div>
    </form>
</div>