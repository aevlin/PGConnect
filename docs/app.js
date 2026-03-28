const demoListings = [
  { id: 1, name: "Skyline Stay Andheri", city: "Mumbai", area: "Andheri East", rent: 14500, sharing: "2-sharing", gender: "Co-ed", beds: 2, rating: 4.8, image: "https://images.pexels.com/photos/1643383/pexels-photo-1643383.jpeg", coords: [19.1197, 72.8468] },
  { id: 2, name: "Cedar Nest Powai", city: "Mumbai", area: "Powai", rent: 18800, sharing: "Single", gender: "Women", beds: 1, rating: 4.7, image: "https://images.pexels.com/photos/1457841/pexels-photo-1457841.jpeg", coords: [19.1176, 72.9060] },
  { id: 3, name: "Metro Living Koramangala", city: "Bangalore", area: "Koramangala", rent: 12500, sharing: "2-sharing", gender: "Co-ed", beds: 2, rating: 4.9, image: "https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg", coords: [12.9352, 77.6245] },
  { id: 4, name: "Indiranagar Loft PG", city: "Bangalore", area: "Indiranagar", rent: 17200, sharing: "Single", gender: "Men", beds: 1, rating: 4.6, image: "https://images.pexels.com/photos/259588/pexels-photo-259588.jpeg", coords: [12.9784, 77.6408] },
  { id: 5, name: "Civil Lines Comfort", city: "Delhi", area: "Civil Lines", rent: 16000, sharing: "2-sharing", gender: "Women", beds: 2, rating: 4.5, image: "https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg", coords: [28.6767, 77.2250] },
  { id: 6, name: "Dwarka Urban PG", city: "Delhi", area: "Dwarka", rent: 11200, sharing: "3-sharing", gender: "Co-ed", beds: 3, rating: 4.4, image: "https://images.pexels.com/photos/2121121/pexels-photo-2121121.jpeg", coords: [28.5921, 77.0460] },
  { id: 7, name: "Harbor House Kakkanad", city: "Kochi", area: "Kakkanad", rent: 9800, sharing: "2-sharing", gender: "Co-ed", beds: 2, rating: 4.7, image: "https://images.pexels.com/photos/1643383/pexels-photo-1643383.jpeg", coords: [10.0159, 76.3419] },
  { id: 8, name: "Marine Pearl Ernakulam", city: "Kochi", area: "Ernakulam", rent: 13200, sharing: "Single", gender: "Women", beds: 1, rating: 4.8, image: "https://images.pexels.com/photos/1457841/pexels-photo-1457841.jpeg", coords: [9.9816, 76.2999] },
  { id: 9, name: "Lakeview Stay Pune", city: "Pune", area: "Hinjewadi", rent: 11800, sharing: "2-sharing", gender: "Co-ed", beds: 2, rating: 4.6, image: "https://images.pexels.com/photos/1454806/pexels-photo-1454806.jpeg", coords: [18.5913, 73.7389] },
  { id: 10, name: "Scholars Hub Hyderabad", city: "Hyderabad", area: "Gachibowli", rent: 10800, sharing: "3-sharing", gender: "Men", beds: 3, rating: 4.3, image: "https://images.pexels.com/photos/271618/pexels-photo-271618.jpeg", coords: [17.4401, 78.3489] },
  { id: 11, name: "South Square Chennai", city: "Chennai", area: "OMR", rent: 12100, sharing: "2-sharing", gender: "Women", beds: 2, rating: 4.5, image: "https://images.pexels.com/photos/259588/pexels-photo-259588.jpeg", coords: [12.9172, 80.2301] },
  { id: 12, name: "Riverfront Co-Live Ahmedabad", city: "Ahmedabad", area: "Navrangpura", rent: 9500, sharing: "3-sharing", gender: "Co-ed", beds: 3, rating: 4.2, image: "https://images.pexels.com/photos/2121121/pexels-photo-2121121.jpeg", coords: [23.0338, 72.5850] }
];

function initPills() {
  const pill = document.querySelector(".pill-toggle");
  if (!pill) return;
  const buttons = Array.from(pill.querySelectorAll("button"));
  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      buttons.forEach((b) => b.classList.remove("active"));
      button.classList.add("active");
    });
  });
}

function listingCard(listing) {
  return `
    <div class="col-md-6">
      <article class="pg-card h-100">
        <img src="${listing.image}" class="w-100" alt="${listing.name}">
        <div class="p-3">
          <div class="d-flex justify-content-between mb-1 small">
            <span>${listing.name}</span>
            <span class="text-warning fw-semibold">Rs. ${listing.rent.toLocaleString()}/mo</span>
          </div>
          <p class="small text-muted mb-2">${listing.area}, ${listing.city}</p>
          <div class="d-flex flex-wrap gap-2">
            <span class="tag">${listing.sharing}</span>
            <span class="tag">${listing.gender}</span>
            <span class="tag">${listing.beds} beds</span>
          </div>
          <div class="mt-3 d-flex justify-content-between align-items-center">
            <span class="small text-muted">${listing.rating} star rating</span>
            <button class="btn btn-sm btn-outline-primary" type="button">View details</button>
          </div>
        </div>
      </article>
    </div>
  `;
}

function filterListings() {
  const city = document.getElementById("cityFilter")?.value.trim().toLowerCase() || "";
  const sharing = document.getElementById("sharingFilter")?.value || "";
  const gender = document.getElementById("genderFilter")?.value || "";
  const budget = document.getElementById("budgetFilter")?.value || "";

  let minBudget = 0;
  let maxBudget = Number.MAX_SAFE_INTEGER;
  if (budget) {
    const [min, max] = budget.split("-").map((value) => Number(value));
    minBudget = Number.isFinite(min) ? min : 0;
    maxBudget = Number.isFinite(max) ? max : Number.MAX_SAFE_INTEGER;
  }

  return demoListings.filter((listing) => {
    const matchesCity = !city || `${listing.city} ${listing.area}`.toLowerCase().includes(city);
    const matchesSharing = !sharing || listing.sharing === sharing;
    const matchesGender = !gender || listing.gender === gender;
    const matchesBudget = listing.rent >= minBudget && listing.rent <= maxBudget;
    return matchesCity && matchesSharing && matchesGender && matchesBudget;
  });
}

function renderListings(listings) {
  const grid = document.getElementById("listingGrid");
  const resultsCount = document.getElementById("resultsCount");
  const nearby = document.getElementById("nearby-results");
  if (!grid) return;

  if (!listings.length) {
    grid.innerHTML = `<div class="col-12"><div class="alert alert-info rounded-4">No demo listings matched this filter. Try a different city or budget.</div></div>`;
  } else {
    grid.innerHTML = listings.map(listingCard).join("");
  }

  if (resultsCount) {
    resultsCount.textContent = `${listings.length} result${listings.length === 1 ? "" : "s"}`;
  }

  if (nearby) {
    nearby.innerHTML = `
      <div class="card border-0 shadow-sm rounded-4 p-3">
        <strong class="mb-2">Demo listings in view</strong>
        <div class="small text-muted">${listings.slice(0, 4).map((item) => `${item.name}, ${item.city}`).join(" • ") || "No listings in this filtered set."}</div>
      </div>
    `;
  }
}

function initMap() {
  const mapEl = document.getElementById("pg-map");
  if (!mapEl || typeof L === "undefined") return;

  const map = L.map("pg-map").setView([20.5937, 78.9629], 5);
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    maxZoom: 19,
    attribution: "&copy; OpenStreetMap contributors"
  }).addTo(map);

  const intro = L.control({ position: "topright" });
  intro.onAdd = function onAdd() {
    const div = L.DomUtil.create("div", "map-intro card p-2 shadow-sm");
    div.innerHTML = "<small><strong>PGConnect Demo Map</strong><br>Static sample listings across India.</small>";
    return div;
  };
  intro.addTo(map);

  const markers = [];

  function syncMarkers(listings) {
    markers.forEach((marker) => map.removeLayer(marker));
    markers.length = 0;

    listings.forEach((listing) => {
      const marker = L.marker(listing.coords).addTo(map).bindPopup(`
        <div style="min-width:220px">
          <img src="${listing.image}" alt="${listing.name}" style="width:100%;height:90px;object-fit:cover;border-radius:8px;margin-bottom:8px">
          <strong>${listing.name}</strong><br>
          <span>${listing.area}, ${listing.city}</span><br>
          <strong>Rs. ${listing.rent.toLocaleString()}</strong> / month
        </div>
      `);
      markers.push(marker);
    });
  }

  syncMarkers(demoListings);

  const form = document.getElementById("heroSearchForm");
  if (form) {
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const filtered = filterListings();
      renderListings(filtered);
      syncMarkers(filtered);
      if (filtered.length) {
        const bounds = L.latLngBounds(filtered.map((item) => item.coords));
        map.fitBounds(bounds.pad(0.18));
      } else {
        map.setView([20.5937, 78.9629], 5);
      }
    });
  }
}

function initAuthDemo() {
  const loginForm = document.getElementById("loginForm");
  if (loginForm) {
    loginForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const alertBox = document.getElementById("loginAlert");
      if (alertBox) {
        alertBox.innerHTML = '<div class="alert alert-info rounded-3">Demo mode on GitHub Pages. Backend login is disabled, but the screen is ready for presentation.</div>';
      }
    });
  }

  const signupForm = document.getElementById("signupForm");
  if (signupForm) {
    signupForm.addEventListener("submit", (event) => {
      event.preventDefault();
      const data = new FormData(signupForm);
      const password = String(data.get("password") || "");
      const confirm = String(data.get("confirm_password") || "");
      const alertBox = document.getElementById("signupAlert");

      if (!alertBox) return;

      if (password !== confirm) {
        alertBox.innerHTML = '<div class="alert alert-danger rounded-3">Passwords do not match.</div>';
        return;
      }

      alertBox.innerHTML = '<div class="alert alert-success rounded-3">Demo account created successfully. On GitHub Pages this is only a visual flow.</div>';
      signupForm.reset();
      const firstRole = signupForm.querySelector('input[name="role"][value="user"]');
      if (firstRole) firstRole.checked = true;
    });
  }
}

document.addEventListener("DOMContentLoaded", () => {
  initPills();
  renderListings(demoListings);
  initMap();
  initAuthDemo();
});
