const REQUIRED_HEADERS = [
  "Inbound Count (SUM)",
  "Inbound Message Date",
  "Routing Stage (in) (Message)",
  "Country (in) (Message)",
  "Macro Tracker (Message)",
  "Account",
  "Social Network",
  "Message Type",
  "Sentiment",
  "Universal Message ID",
];
function validateFormat(data) {
  if (!data || data.length === 0) return false;
  const actualHeaders = Object.keys(data[0]);
  // Check if every required header exists in the uploaded file
  const missing = REQUIRED_HEADERS.filter((h) => !actualHeaders.includes(h));

  if (missing.length > 0) {
    showToast(
      `Invalid Format! Missing columns: ${missing.join(", ")}`,
      "danger",
    );
    return false;
  }
  return true;
}

let dashboardMetrics = null; // Stores data currently displayed on the dashboard
let tempUploadData = null; // Stores data from a file upload BEFORE it is saved to DB

// 1. DATE PICKER LOGIC (Keep as is)
document.addEventListener("DOMContentLoaded", function () {
  const dateDisplay = document.getElementById("dynamicDateDisplay");

  function updateHeader() {
    const start = new Date(document.getElementById("startDate").value);
    const end = new Date(document.getElementById("endDate").value);

    if (!isNaN(start) && !isNaN(end)) {
      const options = { month: "short", day: "numeric", year: "numeric" };

      // Format both dates fully
      const startText = start.toLocaleDateString("en-US", options);
      const endText = end.toLocaleDateString("en-US", options);

      // Display as: Jan 1, 2026 - Feb 28, 2026
      dateDisplay.innerText = `${startText} - ${endText}`;
    }
  }

  const dateConfig = {
    dateFormat: "Y-m-d",
    onChange: updateHeader,
  };

  flatpickr("#startDate", dateConfig);
  flatpickr("#endDate", dateConfig);

  // Run once on load to show initial dates
  updateHeader();

  // Initialize button listeners
  const saveBtn = document.getElementById("dbSaveBtn");
  if (saveBtn) saveBtn.addEventListener("click", saveToDatabase);

  const viewBtn = document.getElementById("viewReportBtn");
  if (viewBtn) viewBtn.addEventListener("click", fetchAndDisplayReport);

  // --- 1. DEFINE TOUR (Outside the if-statement) ---
  const driver = window.driver.js.driver;
  const tour = driver({
    showProgress: true,
    steps: [
      {
        // Only one bracket here
        element: ".prInfo-div",
        popover: {
          title: "📊 Filter Your View",
          description:
            'Adjust the <b>Region</b> and <b>Date Range</b> here, then click "View Report" to update the dashboard metrics.',
          side: "right",
          align: "center",
        },
      },
      {
        element: ".sidebar-section",
        popover: {
          title: "📥 Data Management",
          description:
            "Need to add new records? Use this section to upload your CSV or Excel files directly into the database.",
          side: "right",
          align: "center",
        },
      },
      {
        element: ".report-container",
        popover: {
          title: "📋 Interactive Dashboard",
          description:
            "This is your real-time preview. All charts, sentiments, and metrics will update instantly based on your filters.",
          side: "right",
          align: "center",
        },
      },
      {
        element: "#sendPreviewBtn",
        popover: {
          title: "🚀 Share Your Report",
          description:
            "Ready to send? This will capture the dashboard and download the image into your local folder.",
          side: "left",
          align: "center",
        },
      },
    ],
  });

  // --- 2. ADD BUTTON LISTENER ---
  const startTourBtn = document.getElementById("startTourBtn");
  if (startTourBtn) {
    startTourBtn.addEventListener("click", () => {
      tour.drive();
    });
  }

  // --- 3. AUTO-START LOGIC ---
  if (!localStorage.getItem("dashboard_tour_seen")) {
    tour.drive();
    localStorage.setItem("dashboard_tour_seen", "true");
  }
});

/**
 * HELPER: Formats various date inputs into MySQL YYYY-MM-DD format
 * Handles Excel serial numbers and "MM-DD-YYYY HH:MM" strings
 */
function formatInboundDate(input) {
  if (!input) return null;

  // Handle Excel Serial Numbers
  if (typeof input === "number") {
    const date = new Date(Math.round((input - 25569) * 86400 * 1000));
    return date.toISOString().split("T")[0];
  }

  // Handle String format: "2/28/2026 7:59" or "02-24-2026"
  if (typeof input === "string") {
    const datePart = input.split(" ")[0];
    // Replace slashes with dashes to standardize
    const standardizedDate = datePart.replace(/\//g, "-");
    const parts = standardizedDate.split("-");

    if (parts.length === 3) {
      // Logic to handle both M-D-Y and Y-M-D
      let [p1, p2, p3] = parts;
      if (p1.length === 4)
        return `${p1}-${p2.padStart(2, "0")}-${p3.padStart(2, "0")}`; // YYYY-MM-DD
      return `${p3}-${p1.padStart(2, "0")}-${p2.padStart(2, "0")}`; // MM-DD-YYYY to YYYY-MM-DD
    }
  }
  return input;
}

// --- 2. FULL FILE UPLOAD LISTENER ---
document.getElementById("csvUpload").addEventListener("change", function (e) {
  const file = e.target.files[0];
  if (!file) return;

  const fileName = file.name.toLowerCase();

  const handleFileProcess = (data) => {
    // A. Verify if the user uses the same format
    if (!validateFormat(data)) {
      tempUploadData = null; // Clear any previously loaded data
      this.value = ""; // Clear the file input
      return;
    }

    // B. CLEAN DATA: Convert Inbound Message Date to YYYY-MM-DD for the Database
    const cleanedData = data.map((row) => {
      if (row["Inbound Message Date"]) {
        row["Inbound Message Date"] = formatInboundDate(
          row["Inbound Message Date"],
        );
      }
      return row;
    });

    // Store the verified and cleaned data in the global temp variable
    tempUploadData = cleanedData;

    const statusDiv = document.getElementById("uploadStatus");
    statusDiv.style.display = "block";
    statusDiv.className = "mt-2 small fw-bold text-success text-center";
    statusDiv.innerHTML = `✔ Format Verified (${data.length} rows). Click 'Save to Database'.`;
  };

  // Process Excel Files
  if (fileName.endsWith(".xlsx") || fileName.endsWith(".xls")) {
    const reader = new FileReader();
    reader.onload = function (e) {
      const data = new Uint8Array(e.target.result);
      const workbook = XLSX.read(data, { type: "array", cellDates: false });
      const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
      // Convert sheet to JSON and pass to handler
      handleFileProcess(XLSX.utils.sheet_to_json(firstSheet));
    };
    reader.readAsArrayBuffer(file);
  }
  // Process CSV Files
  else {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      dynamicTyping: true,
      complete: function (results) {
        handleFileProcess(results.data);
      },
    });
  }
});

//SENDING OF EMAIL
function sendReportEmail() {
  const start = document.getElementById("startDate").value;
  const end = document.getElementById("endDate").value;

  fetch("send_email.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      metrics: dashboardMetrics,
      dateRange: `${start} to ${end}`
    })
  })
  .then(res => res.json())
  .then(res => {
    if (res.success) {
      showToast("Email sent successfully!", "success");
    } else {
      showToast("Failed to send email", "danger");
    }
  })
  .catch(err => {
    console.error(err);
    showToast("Error sending email", "danger");
  });
}


// 3. SAVE TO DATABASE LOGIC
async function saveToDatabase() {
  const statusDiv = document.getElementById("uploadStatus");
  const uploadRegion = document.getElementById("uploadRegion").value;
  const date = document.getElementById("startDate").value;
  const fileInput = document.getElementById("csvUpload");

  if (!tempUploadData)
    return showToast("Please upload and process a file first!", "danger");
  if (uploadRegion === "Select Region...")
    return showToast("Please select a region", "warning");

  statusDiv.style.display = "block";
  statusDiv.className = "mt-2 small fw-bold text-primary text-center";
  statusDiv.innerHTML = "Saving to Azure SQL Database...";

  try {
    const response = await fetch("upload.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        region: uploadRegion,
        date: date,
        data: tempUploadData,
      }),
    });

    // 1. Get the raw text first to check if it's actually JSON
    const text = await response.text();
    
    let result;
    try {
        result = JSON.parse(text);
    } catch (e) {
        // If it's NOT JSON, show the raw text (this is where the 'Array' lives!)
        throw new Error("Server sent back non-JSON: " + text.substring(0, 100));
    }

    if (result.status === "success") {
      statusDiv.className = "mt-2 small fw-bold text-success text-center";
      statusDiv.innerHTML = `✔ ${result.message}`;
      showToast(result.message, "success");

      tempUploadData = null;
      if (fileInput) fileInput.value = "";
    } else {
      // Handle errors sent by PHP
      const errorMsg = result.message || result.error || "Unknown Error";
      statusDiv.className = "mt-2 small fw-bold text-danger text-center";
      statusDiv.innerHTML = `❌ ${errorMsg}`;
      showToast(errorMsg, "danger");
    }

  } catch (error) {
    console.error("Save Error:", error);
    statusDiv.className = "mt-2 small fw-bold text-danger text-center";
    statusDiv.innerHTML = `❌ ${error.message}`;
  }
}

// 4. VIEW REPORT LOGIC
async function fetchAndDisplayReport() {
  const region = document.getElementById("viewRegion").value;
  const start = document.getElementById("startDate").value;
  const end = document.getElementById("endDate").value;

  if (region === "Select Region...")
    return showToast("Please select a region to view!", "warning");

  try {
    const response = await fetch(
      `fetch_report.php?region=${region}&start=${start}&end=${end}`,
    );
    const data = await response.json();

    if (!data || data.length === 0) {
      showToast("No data found for the selected filters.", "warning");
    } else {
      updateDashboard(data);
    }
  } catch (e) {
    console.error("Fetch Error:", e);
    showToast(
      "Error fetching data from database. Please contact developer support!",
      "danger",
    );
  }
}

// 5. UPDATE DASHBOARD
function updateDashboard(data) {
  const cleanData = data.filter((row) => row["Inbound Count (SUM)"] != null);
  const total = cleanData.length;

  if (total === 0) return;

  // --- 1. Top Cards (Routing Stages) ---
  const responded = cleanData.filter(
    (row) => row["Routing Stage (in) (Message)"] === "Responded To",
  ).length;
  const nonActionable = cleanData.filter(
    (row) => row["Routing Stage (in) (Message)"] === "Non-Actionable",
  ).length;
  const forResponse = cleanData.filter((row) =>
    ["New", "For Response"].includes(row["Routing Stage (in) (Message)"]),
  ).length;

  document.getElementById("totalSent").innerText = total;
  document.getElementById("totalResponded").innerText = responded;
  document.getElementById("totalRespondedPct").innerText =
    `(${((responded / total) * 100).toFixed(1)}%)`;
  document.getElementById("totalClosed").innerText = nonActionable;
  document.getElementById("totalClosedPct").innerText =
    `(${((nonActionable / total) * 100).toFixed(1)}%)`;
  document.getElementById("forResponse").innerText = forResponse;
  document.getElementById("forResponsePct").innerText =
    `(${((forResponse / total) * 100).toFixed(1)}%)`;

  // Helper Function for Top 4 + Others Logic
  const getTop4AndOthers = (countsObj) => {
    const entries = Object.entries(countsObj).sort((a, b) => b[1] - a[1]);
    if (entries.length <= 4) return entries;
    const top4 = entries.slice(0, 4);
    const othersCount = entries
      .slice(4)
      .reduce((sum, entry) => sum + entry[1], 0);
    top4.push(["Others", othersCount]);
    return top4;
  };

  // --- 2. Performance by Area (Country) - VERTICAL BARS ---
  const areaCounts = {};
  cleanData.forEach((row) => {
    const area = row["Country (in) (Message)"] || "Unknown";
    areaCounts[area] = (areaCounts[area] || 0) + 1;
  });

  const topAreas = getTop4AndOthers(areaCounts);
  const areaContainer = document.getElementById("areaChartContainer");

  if (areaContainer) {
    areaContainer.innerHTML = "";
    const maxAreaCount = Math.max(...topAreas.map((e) => e[1]));
    const colors = ["#071952", "#088395", "#37B7C3", "#64B5F6", "#757575"];

    // FIXED: Added 'index' here so the color logic works
    topAreas.forEach(([area, count], index) => {
      const displayHeight = maxAreaCount > 0 ? (count / maxAreaCount) * 95 : 0;

      // Now 'index' is defined, so this line won't crash the script
      const color = colors[index] || colors[4];

      areaContainer.insertAdjacentHTML(
        "beforeend",
        `
        <div class="bar-wrapper">
          <div class="bar" style="height: ${displayHeight}%; background: ${color};" title="${area}: ${count}">
            <span style="font-size: 15px; position: absolute; text-align:center; width: 100%; top: 2.5px; color: #FFFFFF; font-weight: bold;">
              ${count}
            </span>
          </div>
          <div class="small mt-1" style="font-size: 11px; font-weight: bold; white-space: nowrap;">${area}</div>
        </div>`,
      );
    });
  }

  // --- 3. Performance by Accounts/Handles - HORIZONTAL BARS ---
  const accountCounts = {};
  cleanData.forEach((row) => {
    const acc = row["Account"] || "Unknown";
    accountCounts[acc] = (accountCounts[acc] || 0) + 1;
  });

  const accountContainer = document.getElementById("accountHandlesContainer");
  if (accountContainer) {
    accountContainer.innerHTML = "";
    accountContainer.className = "mt-3";

    const topAccounts = getTop4AndOthers(accountCounts);

    // Define the same color palette
    const colors = ["#071952", "#088395", "#37B7C3", "#64B5F6", "#757575"];

    // Added 'index' here to track the loop count
    topAccounts.forEach(([name, count], index) => {
      const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;

      // Select color based on index
      const color = colors[index] || colors[4];

      accountContainer.insertAdjacentHTML(
        "beforeend",
        `
      <div class="d-flex align-items-center mb-2" style="gap: 12px;">
        <div style="min-width: 200px; display: flex; justify-content: space-between; align-items: center;">
          <span class="fw-bold" style="font-size: 14px; white-space: nowrap; overflow: hidden; max-width: 150px;" title="${name}">
            ${name}
          </span>
          <span class="text-muted" style="font-size: 12px; margin-left: 5px;">
            ${pct}%
          </span>
        </div>

        <div class="flex-grow-1">
          <div class="progress" style="height: 12px; background-color: #e9ecef; border-radius: 10px;">
            <div class="progress-bar" role="progressbar" 
                 style="width: ${pct}%; background-color: ${color}; border-radius: 10px;">
            </div>
          </div>
        </div>
        
        <div style="min-width: 25px; text-align: right; font-size: 12px; color: #888;">
          ${count}
        </div>
      </div>`,
      );
    });
  }

  // --- 4. Customer Journey & Platform ---
  const journey = calculateJourney(cleanData);
  document.getElementById("count-retention").innerText = journey.Retention;
  document.getElementById("count-fans").innerText = journey.Fans;
  document.getElementById("count-usage").innerText = journey.Usage;
  document.getElementById("count-prospecting").innerText = journey.Prospecting;

  const platformCounts = {};
  cleanData.forEach((row) => {
    const platform = row["Social Network"] || "Other";
    platformCounts[platform] = (platformCounts[platform] || 0) + 1;
  });

  const platformList = document.getElementById("platformList");
  platformList.innerHTML = "";
  Object.entries(platformCounts).forEach(([name, count]) => {
    platformList.innerHTML += `
      <div class="d-flex justify-content-between small border-bottom mb-1">
        <span>${name}</span><b>${count}</b>
      </div>`;
  });

  // --- 5. Sentiments ---
  const sentiments = calculateSentiments(cleanData);
  const updateBar = (id, count) => {
    const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
    document.getElementById(`label-${id}`).innerText =
      `${id.charAt(0).toUpperCase() + id.slice(1)} (${count})`;
    document.getElementById(`bar-${id}`).style.width = pct + "%";
  };
  updateBar("positive", sentiments.Positive);
  updateBar("negative", sentiments.Negative);
  updateBar("neutral", sentiments.Neutral);

  dashboardMetrics = {
    total,
    responded,
    nonActionable,
    forResponse,
    journey,
    sentiments,
    rawData: cleanData,
  };
  // --- 6. Message Type (PIE CHART - Column I) ---
  const typeCounts = {};
  cleanData.forEach((row) => {
    const mType = row["Message Type"] || "Unknown";
    typeCounts[mType] = (typeCounts[mType] || 0) + 1;
  });

  const messageTypeContainer = document.getElementById("messageTypeContainer");
  const typeLegend = document.getElementById("typeLegend");

  if (messageTypeContainer && typeLegend) {
    const topTypes = getTop4AndOthers(typeCounts);
    const colors = ["#071952", "#088395", "#37B7C3", "#64B5F6", "#757575"];

    typeLegend.innerHTML = "";
    let svgPaths = "";
    let cumulativePercent = 0;

    topTypes.forEach(([name, count], index) => {
      const percent = count / total;
      const color = colors[index] || colors[4];

      // Calculate SVG coordinates for the slice
      const startX = Math.cos(2 * Math.PI * cumulativePercent);
      const startY = Math.sin(2 * Math.PI * cumulativePercent);
      cumulativePercent += percent;
      const endX = Math.cos(2 * Math.PI * cumulativePercent);
      const endY = Math.sin(2 * Math.PI * cumulativePercent);

      const largeArcFlag = percent > 0.5 ? 1 : 0;

      // Create the path string
      svgPaths += `<path d="M 0 0 L ${startX} ${startY} A 1 1 0 ${largeArcFlag} 1 ${endX} ${endY} Z" fill="${color}"></path>`;

      // Add to Legend
      typeLegend.innerHTML += `
        <div class="d-flex align-items-center mb-1 small">
          <div style="width: 12px; height: 12px; background: ${color}; border-radius: 3px; margin-right: 8px;"></div>
          <span class="text-truncate" style="max-width: 120px;">${name}</span>
          <span class="ms-auto fw-bold">${count}</span>
        </div>`;
    });

    // Inject SVG instead of setting background gradient
    messageTypeContainer.innerHTML = `
      <svg viewBox="-1 -1 2 2" style="transform: rotate(-90deg); width: 100%; height: 100%; display: block;">
        ${svgPaths}
        <circle cx="0" cy="0" r="0.5" fill="white" /> </svg>`;

    // Clear any old gradient backgrounds
    messageTypeContainer.style.background = "none";
  }
}

function calculateJourney(data) {
  const j = { Retention: 0, Fans: 0, Usage: 0, Prospecting: 0 };
  data.forEach((row) => {
    const val = String(row["Macro Tracker (Message)"] || "");
    if (val.includes("Retention")) j.Retention++;
    else if (val.includes("Fans")) j.Fans++;
    else if (val.includes("Usage")) j.Usage++;
    else if (val.includes("Prospecting")) j.Prospecting++;
  });
  return j;
}

function calculateSentiments(data) {
  const s = { Positive: 0, Negative: 0, Neutral: 0 };
  data.forEach((row) => {
    const val = row["Sentiment"];
    if (s.hasOwnProperty(val)) s[val]++;
  });
  return s;
}

document
  .getElementById("sendPreviewBtn")
  .addEventListener("click", function () {
    if (!dashboardMetrics) {
      return showToast("Please view a report first!", "warning");
    }
    sendReportEmail();
  });
//upload section toggle
const toggleBtn = document.getElementById("toggleUploadBtn");
const collapseContent = document.getElementById("uploadCollapseContent");
const toggleIcon = document.getElementById("toggleIcon");

if (toggleBtn) {
  toggleBtn.addEventListener("click", function () {
    const isHidden = collapseContent.style.display === "none";

    if (isHidden) {
      collapseContent.style.display = "block";
      toggleIcon.classList.add("rotate-icon");
    } else {
      collapseContent.style.display = "none";
      toggleIcon.classList.remove("rotate-icon");
    }
  });
}

//Alert messages
/**
 * Modern Alert Replacement
 * @param {string} message - The text to show
 * @param {string} type - 'success', 'danger', 'warning', or 'info'
 */
function showToast(message, type = "info") {
  const toastEl = document.getElementById("liveToast");
  const toastMessage = document.getElementById("toastMessage");

  // Set color based on type
  toastEl.classList.remove(
    "bg-dark",
    "bg-success",
    "bg-danger",
    "bg-warning",
    "bg-info",
  );
  toastEl.classList.add(`bg-${type}`);

  toastMessage.innerText = message;

  const toast = new bootstrap.Toast(toastEl);
  toast.show();
}
/**
 * Precise Dashboard Capture
 * Targets only the report area, ignoring sidebar and topbar
 */
// function captureDashboard() {
//   // Target the specific container for the report
//   const element = document.querySelector(".report-container");

//   if (!element) {
//     return showToast("Dashboard area not found.", "danger");
//   }

//   if (typeof showToast === "function") {
//     showToast("Generating report image...", "info");
//   }

//   const options = {
//     scale: 2, // High resolution
//     useCORS: true, // For external assets
//     backgroundColor: "#f5f6f8", // Matches your dashboard bg
//     // These settings prevent the sidebar/topbar overlap issues
//     scrollX: 0,
//     scrollY: -window.scrollY,
//     windowWidth: document.documentElement.offsetWidth,
//     windowHeight: document.documentElement.offsetHeight,
//   };

//   html2canvas(element, options)
//     .then((canvas) => {
//       const image = canvas.toDataURL("image/jpeg", 0.9);
//       const link = document.createElement("a");

//       const dateStr = new Date().toISOString().slice(0, 10);
//       link.download = `Triage_Report_${dateStr}.jpg`;
//       link.href = image;
//       link.click();

//       if (typeof showToast === "function") {
//         showToast("Report image downloaded!", "success");
//       }
//     })
//     .catch((err) => {
//       console.error("Capture Error:", err);
//       showToast("Capture failed. Try scrolling to the top.", "danger");
//     });
// }

