// Format phone number input
document
  .getElementById("contact_number")
  ?.addEventListener("input", function (e) {
    e.target.value = e.target.value.replace(/[^\d+]/g, "");
  });

// Focus first field with error
document.querySelector("form").addEventListener("submit", function (e) {
  updateClientAddress();
  const invalidFields = this.querySelectorAll(":invalid");
  if (invalidFields.length > 0) {
    e.preventDefault();
    invalidFields[0].focus();
    invalidFields[0].scrollIntoView({
      behavior: "smooth",
      block: "center",
    });
  }
});

// Build the client_address string from the structured fields below,
// exactly the same way clients.php and job_orders.php do it, so the
// saved address always matches what job_orders.php will prefill.
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

  const fullAddress = parts.join(", ");
  document.getElementById("client_address").value = fullAddress;

  const preview = document.getElementById("client_address_preview");
  if (preview) preview.value = fullAddress;
}

// Suggest RDO code based on the selected city
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

document.addEventListener("DOMContentLoaded", function () {
  // Prevent commas in the address-part fields
  document.querySelectorAll('input[pattern="[^,]*"]').forEach((input) => {
    input.addEventListener("keydown", (e) => {
      if (e.key === ",") e.preventDefault();
    });
    input.addEventListener("input", () => {
      input.value = input.value.replace(/,/g, "");
    });
  });

  // Keep the address preview + hidden field in sync as the user types
  ["floor_no", "building_no", "street", "barangay", "zip_code"].forEach(
    (id) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener("input", updateClientAddress);
    },
  );

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

  // Make sure the hidden/preview address reflects the loaded data on first render
  updateClientAddress();
});
