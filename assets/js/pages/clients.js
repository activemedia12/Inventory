function toggleAddClientForm(forceOpen) {
  const body = document.getElementById("addClientBody");
  const chevron = document.getElementById("addClientChevron");
  const isOpen = body.style.display !== "none";
  const open = forceOpen !== undefined ? forceOpen : !isOpen;

  body.style.display = open ? "block" : "none";
  chevron.style.transform = open ? "rotate(180deg)" : "rotate(0deg)";
  sessionStorage.setItem("clientFormOpen", open ? "1" : "0");
}

document.addEventListener("DOMContentLoaded", () => {
  // If we've landed on clients.php with no query string at all (e.g. via the
  // sidebar nav link, rather than a pagination/letter/search link that
  // explicitly carries its own params), restore the last search term the
  // user had typed, so navigating away and back doesn't silently drop it.
  const savedSearch = sessionStorage.getItem("clientSearchTerm");
  if (!window.location.search && savedSearch) {
    const params = new URLSearchParams();
    params.set("search_client", savedSearch);
    params.set("page", "1");
    window.location.search = params.toString();
    return; // navigating away; skip the rest of this handler
  }

  const searchInput = document.getElementById("clientSearchInput");
  if (searchInput && searchInput.value) {
    searchInput.focus();
    searchInput.setSelectionRange(
      searchInput.value.length,
      searchInput.value.length,
    );
  }
});

document.addEventListener("DOMContentLoaded", () => {
  toggleAddClientForm(sessionStorage.getItem("clientFormOpen") === "1");
});

function goToLastProductPage() {
  const last = localStorage.getItem("lastProductPage");
  if (last) {
    window.location.href = last;
  } else {
    window.location.href = "papers.php"; // fallback
  }
}

document.querySelectorAll(".client-item").forEach((item) => {
  item.addEventListener("click", () => {
    const clientId = item.dataset.id;

    // Reset + show modal immediately with a loading state,
    // rather than waiting on the network before showing anything
    document.getElementById("modalClientName").textContent = "Loading...";
    document.getElementById("modalTaxpayer").textContent = "-";
    document.getElementById("modalTIN").textContent = "-";
    document.getElementById("modalRDO").textContent = "-";
    document.getElementById("modalContact").textContent = "-";
    document.getElementById("modalClientBy").textContent = "-";
    document.getElementById("modalAddress").textContent = "-";
    document.getElementById("modalTotalOrders").textContent = "...";
    document.getElementById("modalRecentOrders").innerHTML = "";
    document.getElementById("clientModal").style.display = "flex";

    // Fetch the full client record only now that it's actually needed
    fetch(`get_client.php?id=${clientId}`)
      .then((res) => res.json())
      .then((client) => {
        if (client.error) throw new Error(client.error);

        document.getElementById("modalClientName").textContent =
          client.client_name;
        document.getElementById("modalTaxpayer").textContent =
          client.taxpayer_name || "-";
        document.getElementById("modalTIN").textContent = client.tin || "-";
        document.getElementById("modalRDO").textContent =
          client.rdo_code || "-";
        document.getElementById("modalContact").textContent =
          `${client.contact_person || ""} ${client.contact_number ? `(${client.contact_number})` : ""}`.trim() ||
          "-";
        document.getElementById("modalClientBy").textContent =
          client.client_by || "-";
        document.getElementById("modalAddress").textContent =
          client.client_address || "-";

        // Set up edit button (now matches class "btn-edit")
        const editBtn = document.getElementById("editClientBtn");
        if (editBtn) {
          editBtn.onclick = (e) => {
            e.preventDefault();
            window.location.href = `edit_client.php?id=${client.id}`;
          };
          editBtn.href = `edit_client.php?id=${client.id}`;
        }

        // Set up delete button (now matches class "btn-delete")
        const deleteBtn = document.getElementById("deleteClientBtn");
        if (deleteBtn) {
          deleteBtn.onclick = () => {
            if (confirm("Are you sure you want to delete this client?")) {
              fetch(`delete_client.php?id=${client.id}`, {
                method: "POST",
              })
                .then((res) => res.json())
                .then((data) => {
                  alert(data.message || "Client deleted successfully");
                  item.remove();
                  closeClientModal();
                  if (!document.querySelector(".client-item")) {
                    document.querySelector(".empty-state").style.display =
                      "block";
                  }
                })
                .catch((error) => {
                  console.error("Error:", error);
                  alert("Failed to delete client");
                });
            }
          };
        }
      })
      .catch((error) => {
        console.error("Error fetching client:", error);
        document.getElementById("modalClientName").textContent =
          "Failed to load client";
      });

    // Fetch order data (updated to match new list structure)
    fetch(`get_client_orders.php?client_id=${clientId}`)
      .then((res) => res.json())
      .then((data) => {
        document.getElementById("modalTotalOrders").textContent =
          data.total_orders || "0";

        const ordersList = document.getElementById("modalRecentOrders");
        ordersList.innerHTML = "";

        if (!data.recent_orders || data.recent_orders.length === 0) {
          ordersList.innerHTML =
            '<li class="no-orders">No recent orders found</li>';
        } else {
          data.recent_orders.forEach((order) => {
            const li = document.createElement("li");
            li.innerHTML = `
                                        <span class="order-project">${order.project_name || "Untitled Project"}</span>
                                        <span class="order-date">${formatDate(order.log_date)}</span>
                                    `;
            ordersList.appendChild(li);
          });
        }
      })
      .catch((error) => {
        console.error("Error fetching orders:", error);
        document.getElementById("modalRecentOrders").innerHTML =
          '<li class="error">Failed to load order history</li>';
      });
  });
});

// Close modal (updated for new close button class)
function closeClientModal() {
  const modal = document.getElementById("clientModal");
  if (!modal || modal.style.display === "none" || modal.style.display === "")
    return;

  modal.classList.add("closing");
  setTimeout(() => {
    modal.style.display = "none";
    modal.classList.remove("closing");
  }, 160);
}

document.querySelector("#clientModal .close-btn").onclick = closeClientModal;

// Close when clicking overlay
document
  .querySelector(".modal-overlay")
  .addEventListener("click", closeClientModal);

// Helper function to format dates
function formatDate(dateString) {
  if (!dateString) return "";
  const options = {
    year: "numeric",
    month: "short",
    day: "numeric",
  };
  return new Date(dateString).toLocaleDateString(undefined, options);
}

document
  .getElementById("clientSearchInput")
  .addEventListener("input", function () {
    const query = this.value;
    clearTimeout(window._clientSearchDebounce);
    window._clientSearchDebounce = setTimeout(() => {
      if (query) {
        sessionStorage.setItem("clientSearchTerm", query);
      } else {
        sessionStorage.removeItem("clientSearchTerm");
      }

      const params = new URLSearchParams(window.location.search);
      if (query) {
        params.set("search_client", query);
        params.delete("letter"); // search takes precedence over the letter filter
      } else {
        params.delete("search_client");
      }
      params.set("page", "1"); // reset to page 1 on a new search
      window.location.search = params.toString();
    }, 450);
  });

document.addEventListener("DOMContentLoaded", () => {
  // Block commas from being typed or pasted
  document
    .querySelectorAll('#clientForm input[type="text"], #clientForm textarea')
    .forEach((input) => {
      input.addEventListener("keydown", (e) => {
        if (e.key === ",") e.preventDefault();
      });
      input.addEventListener("input", () => {
        input.value = input.value.replace(/,/g, "");
      });
    });

  // Update address when any field changes
  [
    "floor_no",
    "building_no",
    "street",
    "barangay",
    "city",
    "province",
    "zip_code",
  ].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("input", updateClientAddress);
  });

  // Province → City dropdown
  const province = document.getElementById("province");
  const city = document.getElementById("city");

  if (province && city) {
    province.addEventListener("change", function () {
      const selectedProvince = this.value;
      city.innerHTML = '<option value="">Select City</option>';
      updateClientAddress();

      if (!selectedProvince) return;

      fetch(`get_cities.php?province=${encodeURIComponent(selectedProvince)}`)
        .then((res) => res.json())
        .then((cities) => {
          cities.forEach((cityName) => {
            const option = document.createElement("option");
            option.value = cityName;
            option.textContent = cityName;
            city.appendChild(option);
          });
        });
    });

    city.addEventListener("change", () => {
      suggestRDO();
      updateClientAddress();
    });
  }

  const scrollPos = sessionStorage.getItem("clientsScrollY");
  if (scrollPos !== null) {
    window.scrollTo(0, parseInt(scrollPos));
  }
});

window.addEventListener("beforeunload", function () {
  sessionStorage.setItem("clientsScrollY", window.scrollY);
});

// Construct full client address string
function updateClientAddress() {
  const floor = document.getElementById("floor_no").value.trim();
  const building = document.getElementById("building_no").value.trim();
  const street = document.getElementById("street").value.trim();
  const barangayEl = document.getElementById("barangay");
  const barangay = barangayEl.value
    .trim()
    .replace(/\b\w/g, (c) => c.toUpperCase());
  barangayEl.value = barangay;

  const city = document.getElementById("city").value.trim();
  const province = document.getElementById("province").value.trim();
  const zip = document.getElementById("zip_code").value.trim();

  const parts = [];
  if (floor) parts.push(floor);
  if (building) parts.push(building);
  if (street) parts.push(street);
  if (barangay) parts.push("Brgy. " + barangay);
  if (city) parts.push(city);
  if (province) parts.push(province);
  if (zip) parts.push(zip);

  document.getElementById("client_address").value = parts.join(", ");
}

// Suggest RDO code based on city value
function suggestRDO() {
  const city = document.getElementById("city").value.trim();
  const rdoInput = document.getElementById("rdo_code");

  const matchedCity = Object.keys(rdoMapping).find((key) =>
    city.toLowerCase().includes(key.toLowerCase()),
  );

  if (matchedCity) {
    rdoInput.value = `${rdoMapping[matchedCity]} - ${matchedCity}`;
  }
}

const rdoMapping = {
  "Laoag City, Ilocos Norte": "001",
  "Vigan, Ilocos Sur": "002",
  "San Fernando, La Union": "003",
  "Calasiao, West Pangasinan": "004",
  "Alaminos, Pangasinan": "005",
  "Urdaneta, Pangasinan": "006",
  "Bangued, Abra": "007",
  "Baguio City": "008",
  "La Trinidad, Benguet": "009",
  "Bontoc, Mt. Province": "010",
  "Tabuk City, Kalinga": "011",
  "Lagawe, Ifugao": "012",
  "Tuguegarao, Cagayan": "013",
  "Bayombong, Nueva Vizcaya": "014",
  "Naguilian, Isabela": "015",
  "Cabarroguis, Quirino": "016",
  "Tarlac City, Tarlac": "17A",
  "Paniqui, Tarlac": "17B",
  "Olongapo City": "018",
  "Subic Bay Freeport Zone": "019",
  "Balanga, Bataan": "020",
  "North Pampanga": "21A",
  "South Pampanga": "21B",
  "Clark Freeport Zone": "21C",
  "Baler, Aurora": "022",
  "North Nueva Ecija": "23A",
  "South Nueva Ecija": "23B",
  "Valenzuela City": "024",
  "Plaridel, Bulacan": "25A (now RDO West Bulacan)",
  "Sta. Maria, Bulacan": "25B (now RDO East Bulacan)",
  "Malabon-Navotas": "026",
  "Caloocan City": "027",
  Novaliches: "028",
  "Tondo – San Nicolas": "029",
  Binondo: "030",
  "Sta. Cruz": "031",
  "Quiapo-Sampaloc-San Miguel-Sta. Mesa": "032",
  "Intramuros-Ermita-Malate": "033",
  "Paco-Pandacan-Sta. Ana-San Andres": "034",
  Romblon: "035",
  "Puerto Princesa": "036",
  "San Jose, Occidental Mindoro": "037",
  "North Quezon City": "038",
  "South Quezon City": "039",
  Cubao: "040",
  "Mandaluyong City": "041",
  "San Juan": "042",
  Pasig: "043",
  "Taguig-Pateros": "044",
  Marikina: "045",
  "Cainta-Taytay": "046",
  "East Makati": "047",
  "West Makati": "048",
  "North Makati": "049",
  "South Makati": "050",
  "Pasay City": "051",
  Parañaque: "052",
  "Las Piñas City": "53A",
  "Muntinlupa City": "53B",
  "Trece Martirez City, East Cavite": "54A",
  "Kawit, West Cavite": "54B",
  "San Pablo City": "055",
  "Calamba, Laguna": "056",
  "Biñan, Laguna": "057",
  "Batangas City": "058",
  "Lipa City": "059",
  "Lucena City": "060",
  "Gumaca, Quezon": "061",
  "Boac, Marinduque": "062",
  "Calapan, Oriental Mindoro": "063",
  "Talisay, Camarines Norte": "064",
  "Naga City": "065",
  "Iriga City": "066",
  "Legazpi City, Albay": "067",
  "Sorsogon, Sorsogon": "068",
  "Virac, Catanduanes": "069",
  "Masbate, Masbate": "070",
  "Kalibo, Aklan": "071",
  "Roxas City": "072",
  "San Jose, Antique": "073",
  "Iloilo City": "074",
  "Zarraga, Iloilo City": "075",
  "Victorias City, Negros Occidental": "076",
  "Bacolod City": "077",
  "Binalbagan, Negros Occidental": "078",
  "Dumaguete City": "079",
  "Mandaue City": "080",
  "Cebu City North": "081",
  "Cebu City South": "082",
  "Talisay City, Cebu": "083",
  "Tagbilaran City": "084",
  "Catarman, Northern Samar": "085",
  "Borongan, Eastern Samar": "086",
  "Calbayog City, Samar": "087",
  "Tacloban City": "088",
  "Ormoc City": "089",
  "Maasin, Southern Leyte": "090",
  "Dipolog City": "091",
  "Pagadian City, Zamboanga del Sur": "092",
  "Zamboanga City, Zamboanga del Sur": "093A",
  "Ipil, Zamboanga Sibugay": "093B",
  "Isabela, Basilan": "094",
  "Jolo, Sulu": "095",
  "Bongao, Tawi-Tawi": "096",
  "Gingoog City": "097",
  "Cagayan de Oro City": "098",
  "Malaybalay City, Bukidnon": "099",
  "Ozamis City": "100",
  "Iligan City": "101",
  "Marawi City": "102",
  "Butuan City": "103",
  "Bayugan City, Agusan del Sur": "104",
  "Surigao City": "105",
  "Tandag, Surigao del Sur": "106",
  "Cotabato City": "107",
  "Kidapawan, North Cotabato": "108",
  "Tacurong, Sultan Kudarat": "109",
  "General Santos City": "110",
  "Koronadal City, South Cotabato": "111",
  "Tagum, Davao del Norte": "112",
  "West Davao City": "113A",
  "East Davao City": "113B",
  "Mati, Davao Oriental": "114",
  "Digos, Davao del Sur": "115",
};
