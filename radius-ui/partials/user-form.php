<!-- partials/user-form.php -->
<div class="card card-body shadow-sm mb-4">
    <h5 class="card-title mb-3">🎫 Generate Voucher Sekali Pakai</h5>
    <form hx-post="user-add.php" 
          hx-target="#user-list-container" 
          hx-swap="innerHTML"
          hx-on::after-request="if(event.detail.successful) this.reset()">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="form-label fw-bold">Username / Kode</label>
                <input type="text" name="username" class="form-control" placeholder="misal: vch123" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Password</label>
                <input type="text" name="password" class="form-control" placeholder="Password" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Speed Limit</label>
                <input type="text" name="rate_limit" class="form-control" placeholder="Contoh: 1M/2M">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Masa Aktif</label>
                <select name="validity" class="form-select">
                    <option value="180">3 Menit (Uji Coba)</option>
                    <option value="3600" selected>1 Jam</option>
                    <option value="7200">2 Jam</option>
                    <option value="18000">5 Jam</option>
                    <option value="86400">1 Hari (24 Jam)</option>
                </select>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-success px-4">+ Buat Voucher</button>
            </div>
        </div>
    </form>
</div>