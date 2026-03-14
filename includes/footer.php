<?php
// Standard footer include for PGConnect
?>
<footer class="py-3">
	<div class="container d-flex flex-wrap justify-content-between">
		<span>© 2025 PGConnect. Built for India.</span>
		<span class="text-muted">HTML · CSS · Bootstrap 5 · PHP · MySQL · LeafletJS</span>
	</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
	// Smooth scrolling for anchor links
	document.querySelectorAll('a[href^="#"]').forEach(anchor => {
		anchor.addEventListener('click', function (e) {
			e.preventDefault();
			const target = document.querySelector(this.getAttribute('href'));
			if (target) target.scrollIntoView({ behavior: 'smooth' });
		});
	});

	// Navbar scroll effect
	window.addEventListener('scroll', () => {
		const navbar = document.querySelector('.navbar');
		if (!navbar) return;
		navbar.style.background = window.scrollY > 50
			? 'rgba(255,255,255,0.95)'
			: 'rgba(255,255,255,0.9)';
	});
</script>

<!-- Empty selection modal -->
<div class="modal fade" id="emptySelectionModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-sm modal-dialog-centered">
		<div class="modal-content">
			<div class="modal-body text-center py-4">
				<h5 class="mb-2">No selection</h5>
				<p class="mb-3 small text-muted">Please select at least one listing to perform this action.</p>
				<button class="btn btn-primary" data-bs-dismiss="modal">OK</button>
			</div>
		</div>
	</div>
</div>

<!-- Lightbox modal -->
<div class="modal fade" id="imageLightbox" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-lg">
		<div class="modal-content bg-transparent border-0">
			<div class="modal-body p-0">
				<img id="lightboxImg" src="" class="img-fluid rounded" alt="">
			</div>
		</div>
	</div>
</div>

<script>
// Select all helper for tables
document.addEventListener('DOMContentLoaded', function () {
	const selectAll = document.getElementById('selectAll');
	if (selectAll) {
		selectAll.addEventListener('change', function () {
			document.querySelectorAll('input[name="pg_ids[]"]').forEach(cb => cb.checked = this.checked);
		});
	}
	const adminSelectAll = document.getElementById('adminSelectAll');
	if (adminSelectAll) {
		adminSelectAll.addEventListener('change', function () {
			document.querySelectorAll('#adminBulkForm input[name="pg_ids[]"]').forEach(cb => cb.checked = this.checked);
		});
	}

	// Lightbox: open image in modal on click
	document.querySelectorAll('img').forEach(img => {
		img.addEventListener('click', function (e) {
			const src = this.getAttribute('src');
			if (!src) return;
			// only open for images inside card/gallery (heuristic)
			if (this.closest('.card') || this.closest('.pg-card')) {
				const lb = new bootstrap.Modal(document.getElementById('imageLightbox'));
				document.getElementById('lightboxImg').setAttribute('src', src);
				lb.show();
			}
		});
	});
});
</script>

	<script>
	// Bulk action confirmation and empty-selection guard
	['adminBulkForm','ownerBulkForm'].forEach(function(formId) {
		const form = document.getElementById(formId);
		if (!form) return;
		form.addEventListener('submit', function(e) {
			// event.submitter holds the button that triggered submit in modern browsers
			const submitter = e.submitter || document.activeElement;
			const action = submitter && submitter.value ? submitter.value : (form.querySelector('[name="bulk_action"]') ? form.querySelector('[name="bulk_action"]').value : '');
			const checkboxes = form.querySelectorAll('input[name="pg_ids[]"]:checked');
			if (!checkboxes.length) {
				e.preventDefault();
				var emptyModal = new bootstrap.Modal(document.getElementById('emptySelectionModal'));
				emptyModal.show();
				return false;
			}
			// confirmation for destructive actions
			if (['delete','reject'].includes(action)) {
				if (!confirm('This action is destructive. Are you sure you want to proceed?')) {
					e.preventDefault();
					return false;
				}
			}
			// approve and mark_draft are non-destructive; proceed
		});
	});
	</script>

<script>
// Handle per-row admin actions using fetch to avoid nested form issues
document.addEventListener('click', function (e) {
	const btn = e.target.closest('.admin-action');
	if (!btn) return;
	const id = btn.getAttribute('data-id');
	const action = btn.getAttribute('data-action');
	if (!id || !action) return;
	if (['reject'].includes(action)) {
		if (!confirm('This action is destructive. Are you sure?')) return;
	}
	// send POST
	fetch('<?php echo BASE_URL; ?>/admin/admin-approve.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'pg_id=' + encodeURIComponent(id) + '&action=' + encodeURIComponent(action)
	}).then(r => {
		if (r.ok) {
			location.reload();
		} else {
			alert('Failed to perform action');
		}
	}).catch(err => { alert('Error: ' + err.message); });
});
</script>

<script>
	// Favorite toggle handler (handles .fav-btn click anywhere)
document.addEventListener('click', function (e) {
	const b = e.target.closest('.fav-btn');
	if (!b) return;
	const pgId = b.getAttribute('data-pg');
	if (!pgId) return;
	// optimistically toggle UI
	const original = b.innerText;
	b.disabled = true;
	fetch('<?php echo BASE_URL; ?>/backend/toggle_favorite.php', {
		method: 'POST',
		headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
		body: 'pg_id=' + encodeURIComponent(pgId)
	}).then(r => r.json()).then(j => {
		if (j && j.ok) {
			// toggle heart state
			b.classList.toggle('active', j.action === 'added');
			if (b.tagName.toLowerCase() === 'button' && b.classList.contains('fav-heart-btn')) {
				// no text change for heart button
			} else {
				b.innerText = j.action === 'added' ? 'Unsave' : 'Save';
			}
			// update favorites badge
			if (typeof refreshFavCount === 'function') {
				refreshFavCount();
			} else {
				fetch('<?php echo BASE_URL; ?>/backend/fav_count.php').then(r => r.json()).then(j2 => {
					const badge = document.getElementById('favCountBadge');
					if (badge && j2 && typeof j2.count !== 'undefined') badge.innerText = j2.count;
				}).catch(()=>{});
			}
		} else if (j && j.error === 'auth_required') {
			alert('Please login to save PGs');
			window.location.href = '<?php echo BASE_URL; ?>/backend/login.php';
		} else {
			alert('Failed to update saved PGs');
			if (!b.classList.contains('fav-heart-btn')) b.innerText = original;
		}
	}).catch(err => {
		alert('Error: ' + err.message);
		if (!b.classList.contains('fav-heart-btn')) b.innerText = original;
	}).finally(() => { b.disabled = false; });
});
</script>

<script>
// try to update favorites badge if function available on the page
try { if (typeof refreshFavCount === 'function') refreshFavCount(); } catch(e) {}
</script>

<script>
// Update chat unread badge
(function(){
  const badge = document.getElementById('chatCountBadge');
  if (!badge) return;
  fetch('<?php echo BASE_URL; ?>/backend/unread_count.php')
    .then(r => r.json())
    .then(j => {
      const c = (j && typeof j.count !== 'undefined') ? j.count : 0;
      badge.innerText = c;
      badge.style.display = c > 0 ? 'inline-block' : 'none';
    }).catch(()=>{});
})();
</script>

<script>
// Update owner booking-request badge
(function(){
  const badge = document.getElementById('ownerBookingCountBadge');
  if (!badge) return;
  fetch('<?php echo BASE_URL; ?>/backend/owner_booking_count.php')
    .then(r => r.json())
    .then(j => {
      const c = (j && typeof j.count !== 'undefined') ? j.count : 0;
      badge.innerText = c;
      badge.style.display = c > 0 ? 'inline-block' : 'none';
    }).catch(()=>{});
})();
</script>

<script>
// Notification center badge/list
(function(){
  const badge = document.getElementById('notificationCountBadge');
  const listWrap = document.getElementById('notificationListWrap');
  const markBtn = document.getElementById('markAllNotificationsRead');
  if (!badge || !listWrap) return;

  function esc(s){
    return String(s || '').replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }

  function loadCount(){
    fetch('<?php echo BASE_URL; ?>/backend/notifications_count.php')
      .then(r => r.json())
      .then(j => {
        const c = (j && typeof j.count !== 'undefined') ? j.count : 0;
        badge.innerText = c;
        badge.style.display = c > 0 ? 'inline-block' : 'none';
      }).catch(()=>{});
  }

  function loadList(){
    fetch('<?php echo BASE_URL; ?>/backend/notifications_list.php')
      .then(r => r.json())
      .then(j => {
        if (!j || !j.ok || !Array.isArray(j.items) || j.items.length === 0) {
          listWrap.innerHTML = '<div class="px-3 py-2 small text-muted">No notifications.</div>';
          return;
        }
        let html = '';
        j.items.forEach(it => {
          const title = esc(it.title);
          const msg = esc(it.message);
          const dt = esc(it.created_at);
          const url = it.link ? esc(it.link) : '';
          html += '<a class="dropdown-item small border-bottom py-2' + (it.is_read == 0 ? ' bg-light' : '') + '" href="' + (url || '#') + '">';
          html += '<div class="fw-semibold">' + title + '</div>';
          if (msg) html += '<div class="text-muted">' + msg + '</div>';
          html += '<div class="text-muted" style="font-size:11px;">' + dt + '</div></a>';
        });
        listWrap.innerHTML = html;
      }).catch(()=>{});
  }

  loadCount();
  loadList();

  if (markBtn) {
    markBtn.addEventListener('click', function(e){
      e.preventDefault();
      fetch('<?php echo BASE_URL; ?>/backend/notifications_mark_read.php', { method: 'POST' })
        .then(() => { loadCount(); loadList(); })
        .catch(()=>{});
    });
  }
})();
</script>

<script>
// Trigger saved-search alert checks for logged-in users (best effort, lightweight)
(function(){
  <?php if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'user'): ?>
    fetch('<?php echo BASE_URL; ?>/backend/run_saved_search_alerts.php').catch(()=>{});
  <?php endif; ?>
})();
</script>

<script>
// Init compare badge from session if present (server sets data attribute)
(function(){
  const badge = document.getElementById('compareCountBadge');
  if (!badge) return;
  const count = badge.getAttribute('data-count');
  if (count !== null) badge.innerText = count;
})();
</script>

<script>
// Compare toggle handler
document.addEventListener('click', function (e) {
  const b = e.target.closest('.compare-btn');
  if (!b) return;
  const pgId = b.getAttribute('data-pg');
  if (!pgId) return;
  b.disabled = true;
  fetch('<?php echo BASE_URL; ?>/backend/compare_toggle.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'pg_id=' + encodeURIComponent(pgId)
    }).then(r => r.json()).then(j => {
      if (j && j.ok) {
        b.classList.toggle('active', j.action === 'added');
        b.innerText = j.action === 'added' ? 'Compared' : 'Compare';
        const badge = document.getElementById('compareCountBadge');
        if (badge) badge.innerText = j.count || 0;
      } else if (j && j.error === 'auth_required') {
        alert('Please login to compare PGs');
        window.location.href = '<?php echo BASE_URL; ?>/backend/login.php';
      } else {
        alert('Unable to update compare list right now.');
      }
    }).catch(()=>{
      alert('Unable to update compare list right now.');
    }).finally(()=>{ b.disabled = false; });
});
</script>

<script>
// PWA service worker
(function(){
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function(){
      navigator.serviceWorker.register('<?php echo BASE_URL; ?>/sw.js').catch(function(){});
    });
  }
})();
</script>
