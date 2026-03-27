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

// Global variables for persistence across functions
let dashboardMetrics = null;
let tempUploadData = null;
let datePickerStart = null;
let datePickerEnd = null;
let charts = {};

function validateFormat(data) {
  if (!data || data.length === 0) return false;
  const actualHeaders = Object.keys(data[0]);
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

document.addEventListener("DOMContentLoaded", function () {
  // 1. Initial Setup
  initDatePicker("aftercare");

  // 2. Handle Report Category Toggle
  const categorySelect = document.getElementById("reportCategory");
  if (categorySelect) {
    categorySelect.addEventListener("change", function () {
      const isRSCC = this.value === "rscc";

      // Toggle View Containers
      document.getElementById("afterCareView").style.display = isRSCC
        ? "none"
        : "block";
      document.getElementById("rsccView").style.display = isRSCC
        ? "block"
        : "none";

      // Re-initialize Date Picker for Month vs Day
      initDatePicker(this.value);
      updateHeader();
    });
  }

  // 3. Button Listeners
  const saveBtn = document.getElementById("dbSaveBtn");
  if (saveBtn) saveBtn.addEventListener("click", saveToDatabase);

  const viewBtn = document.getElementById("viewReportBtn");
  if (viewBtn) viewBtn.addEventListener("click", fetchAndDisplayReport);

  const sendPreviewBtn = document.getElementById("sendPreviewBtn");
  if (sendPreviewBtn) {
    sendPreviewBtn.addEventListener("click", function () {
      if (
        !dashboardMetrics &&
        document.getElementById("reportCategory").value === "aftercare"
      ) {
        return showToast("Please view a report first!", "warning");
      }
      const emailModalEl = document.getElementById("emailModal");
      const modal = new bootstrap.Modal(emailModalEl);
      modal.show();
    });
  }

  const confirmSendBtn = document.getElementById("confirmSendBtn");
  if (confirmSendBtn) {
    confirmSendBtn.addEventListener("click", function () {
      const emailInput = document.getElementById("destinationEmail");
      const emailValue = emailInput.value.trim();
      const errorDiv = document.getElementById("emailError");

      if (!emailValue || !emailValue.includes("@")) {
        errorDiv.style.display = "block";
        return;
      }

      errorDiv.style.display = "none";
      const modalElement = document.getElementById("emailModal");
      const modalInstance = bootstrap.Modal.getInstance(modalElement);
      modalInstance.hide();

      sendDashboardEmail(emailValue);
    });
  }

  // 4. Tour Initialization
  const driver = window.driver.js.driver;
  const tour = driver({
    showProgress: true,
    steps: [
      {
        element: ".prInfo-div",
        popover: {
          title: "📊 Filter Your View",
          description:
            "Adjust the <b>Report Category</b>, <b>Region</b> and <b>Date Range</b> here.",
          side: "right",
          align: "center",
        },
      },
      {
        element: ".sidebar-section",
        popover: {
          title: "📥 Data Management",
          description: "Upload your CSV or Excel files here.",
          side: "right",
          align: "center",
        },
      },
      {
        element: "#sendPreviewBtn",
        popover: {
          title: "🚀 Share Your Report",
          description: "Capture the dashboard and send it via email.",
          side: "left",
          align: "center",
        },
      },
    ],
  });

  const startTourBtn = document.getElementById("startTourBtn");
  if (startTourBtn) {
    startTourBtn.addEventListener("click", () => tour.drive());
  }

  if (!localStorage.getItem("dashboard_tour_seen")) {
    tour.drive();
    localStorage.setItem("dashboard_tour_seen", "true");
  }
});

function initDatePicker(mode) {
  // Destroy existing instances to allow re-config
  if (datePickerStart) datePickerStart.destroy();
  if (datePickerEnd) datePickerEnd.destroy();

  const isRSCC = mode === "rscc";

  const config = {
    dateFormat: isRSCC ? "Y-m" : "Y-m-d",
    altInput: true,
    altFormat: isRSCC ? "F Y" : "F j, Y",
    onChange: updateHeader,
  };

  datePickerStart = flatpickr("#startDate", config);
  datePickerEnd = flatpickr("#endDate", config);
}

function updateHeader() {
  const startInput = document.getElementById("startDate");
  const endInput = document.getElementById("endDate");
  const afterCareDisplay = document.getElementById("dynamicDateDisplay");
  const rsccDisplay = document.getElementById("rsccDateDisplay");

  if (startInput.value && endInput.value) {
    const start = new Date(startInput.value);
    const end = new Date(endInput.value);

    const options = { month: "short", day: "numeric", year: "numeric" };
    const monthOptions = { month: "long", year: "numeric" };

    const startText = start.toLocaleDateString("en-US", options);
    const endText = end.toLocaleDateString("en-US", options);

    if (afterCareDisplay)
      afterCareDisplay.innerText = `${startText} - ${endText}`;
    if (rsccDisplay) {
      rsccDisplay.innerText = `${start.toLocaleDateString("en-US", monthOptions)} - ${end.toLocaleDateString("en-US", monthOptions)}`;
    }
  }
}

async function fetchAndDisplayReport() {
  const category = document.getElementById("reportCategory").value;
  const region = document.getElementById("viewRegion").value;
  const start = document.getElementById("startDate").value;
  const end = document.getElementById("endDate").value;

  // Validation
  if (region === "Select Region...") return showToast("Please select a region!", "warning");
  if (!start || !end) return showToast("Please select a date range!", "warning");

  try {
    // 1. Build the query string
    const url = `fetch_report.php?region=${encodeURIComponent(region)}&start=${start}&end=${end}`;
    
    // 2. Fetch the data
    const response = await fetch(url);
    const data = await response.json();

    if (!data || data.length === 0) {
      return showToast("No data found for the selected filters.", "warning");
    }

    // 3. Conditional Execution: Only run the function for the selected category
    if (category === "rscc") {
      console.log("Fetching for RSCC Performance...");
      renderRSCCCharts(data); 
      // After-care logic is ignored here
    } else if (category === "aftercare") {
      console.log("Fetching for After Care Service...");
      updateDashboard(data); 
      // RSCC logic is ignored here
    }

  } catch (e) {
    console.error("Fetch Error:", e);
    showToast("Error fetching data. Check database connection.", "danger");
  }
}

function renderRSCCCharts(data) {
  const monthlyData = {};

  data.forEach((row) => {
    const dateStr = row["Inbound Message Date"];
    if (!dateStr) return;

    const d = new Date(dateStr);
    const month = d.toLocaleString("default", {
      month: "short",
      year: "numeric",
    });
    const count = parseInt(row["Inbound Count (SUM)"]) || 0;
    const stage = (row["Routing Stage (in) (Message)"] || "").toLowerCase();

    if (!monthlyData[month]) {
      monthlyData[month] = { sent: 0, responded: 0 };
    }

    monthlyData[month].sent += count;
    if (stage.includes("responded")) {
      monthlyData[month].responded += count;
    }
  });

  const labels = Object.keys(monthlyData).sort(
    (a, b) => new Date(a) - new Date(b),
  );
  const sentValues = labels.map((m) => monthlyData[m].sent);
  const respValues = labels.map((m) => monthlyData[m].responded);

  renderSingleChart(
    "chartSent",
    "Total Sent to SCC",
    labels,
    sentValues,
    "#071952",
    "rgba(7, 25, 82, 0.05)",
  );
  renderSingleChart(
    "chartResponded",
    "Total Responded",
    labels,
    respValues,
    "#088395",
    "rgba(8, 131, 149, 0.05)",
  );
}

function renderSingleChart(id, label, labels, values, color, bgColor) {
  const ctx = document.getElementById(id);
  if (!ctx) return;

  if (charts[id]) charts[id].destroy();

  charts[id] = new Chart(ctx, {
    type: "line",
    data: {
      labels: labels,
      datasets: [
        {
          label: label,
          data: values,
          borderColor: color,
          fill: true,
          backgroundColor: bgColor,
          tension: 0.4,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
    },
  });
}

function updateDashboard(data) {
  const cleanData = data.filter((row) => row["Inbound Count (SUM)"] != null);
  const total = cleanData.length;
  if (total === 0) return;

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

  // Helper for Top 4 charts
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

  // Area Chart
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
    topAreas.forEach(([area, count], index) => {
      const displayHeight = maxAreaCount > 0 ? (count / maxAreaCount) * 95 : 0;
      areaContainer.insertAdjacentHTML(
        "beforeend",
        `
        <div class="bar-wrapper">
          <div class="bar" style="height: ${displayHeight}%; background: ${colors[index] || colors[4]};" title="${area}: ${count}">
            <span class="bar-label">${count}</span>
          </div>
          <div class="small mt-1 fw-bold text-nowrap" style="font-size: 11px;">${area}</div>
        </div>`,
      );
    });
  }

  // Account Handles
  const accountCounts = {};
  cleanData.forEach((row) => {
    const acc = row["Account"] || "Unknown";
    accountCounts[acc] = (accountCounts[acc] || 0) + 1;
  });
  const accountContainer = document.getElementById("accountHandlesContainer");
  if (accountContainer) {
    accountContainer.innerHTML = "";
    const topAccounts = getTop4AndOthers(accountCounts);
    const colors = ["#071952", "#088395", "#37B7C3", "#64B5F6", "#757575"];
    topAccounts.forEach(([name, count], index) => {
      const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
      accountContainer.insertAdjacentHTML(
        "beforeend",
        `
        <div class="d-flex align-items-center gap-2 w-100">
          <div style="min-width: 160px;"><span class="fw-bold small">${name}</span></div>
          <div style="width: 45px; text-align: right;"><span class="text-muted small">${pct}%</span></div>
          <div class="flex-grow-1">
            <div class="progress-bg" style="height: 10px; background: #eee; border-radius: 5px; overflow: hidden;">
              <div style="width: ${pct}%; height: 100%; background: ${colors[index] || colors[4]}; transition: width 0.5s;"></div>
            </div>
          </div>
          <div style="min-width: 35px; text-align: right; font-size: 12px;">${count}</div>
        </div>`,
      );
    });
  }

  // Journey
  const journey = calculateJourney(cleanData);
  document.getElementById("count-retention").innerText = journey.Retention;
  document.getElementById("count-fans").innerText = journey.Fans;
  document.getElementById("count-usage").innerText = journey.Usage;
  document.getElementById("count-prospecting").innerText = journey.Prospecting;

  // Platform List
  const platformCounts = {};
  cleanData.forEach((row) => {
    const platform = row["Social Network"] || "Other";
    platformCounts[platform] = (platformCounts[platform] || 0) + 1;
  });
  const platformList = document.getElementById("platformList");
  platformList.innerHTML = "";
  Object.entries(platformCounts).forEach(([name, count]) => {
    platformList.innerHTML += `<div class="d-flex justify-content-between small border-bottom mb-1"><span>${name}</span><b>${count}</b></div>`;
  });

  // Sentiments
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

  // Message Type Donut
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
      const startX = Math.cos(2 * Math.PI * cumulativePercent);
      const startY = Math.sin(2 * Math.PI * cumulativePercent);
      cumulativePercent += percent;
      const endX = Math.cos(2 * Math.PI * cumulativePercent);
      const endY = Math.sin(2 * Math.PI * cumulativePercent);
      const largeArcFlag = percent > 0.5 ? 1 : 0;
      svgPaths += `<path d="M 0 0 L ${startX} ${startY} A 1 1 0 ${largeArcFlag} 1 ${endX} ${endY} Z" fill="${color}"></path>`;
      typeLegend.innerHTML += `<div class="d-flex align-items-center mb-1 small"><div style="width: 12px; height: 12px; background: ${color}; border-radius: 3px; margin-right: 8px;"></div><span>${name}</span><span class="ms-auto fw-bold">${count}</span></div>`;
    });
    messageTypeContainer.innerHTML = `<svg viewBox="-1 -1 2 2" style="transform: rotate(-90deg); width: 100%; height: 100%; display: block;">${svgPaths}<circle cx="0" cy="0" r="0.5" fill="white" /></svg>`;
  }

  dashboardMetrics = { total, rawData: cleanData };
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

async function saveToDatabase() {
  const statusDiv = document.getElementById("uploadStatus");
  const uploadRegion = document.getElementById("uploadRegion").value;
  const fileInput = document.getElementById("csvUpload");

  if (!tempUploadData)
    return showToast("Please upload a file first!", "danger");
  if (uploadRegion === "Select Region...")
    return showToast("Please select a region", "warning");

  statusDiv.style.display = "block";
  statusDiv.innerHTML = "Saving to Database...";

  try {
    const response = await fetch("upload.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ region: uploadRegion, data: tempUploadData }),
    });
    const result = await response.json();
    if (result.status === "success") {
      showToast(result.message, "success");
      tempUploadData = null;
      fileInput.value = "";
      statusDiv.style.display = "none";
    } else {
      showToast(result.message, "danger");
    }
  } catch (error) {
    showToast("Save failed.", "danger");
  }
}

function sendDashboardEmail(targetEmail) {
  const category = document.getElementById("reportCategory").value;
  const element = document.querySelector(
    category === "rscc"
      ? "#rsccView .report-container"
      : "#afterCareView .report-container",
  );

  showToast("Capturing report...", "info");

  html2canvas(element, {
    scale: 1,
    useCORS: true,
    backgroundColor: "#ffffff",
  }).then((canvas) => {
    const base64Image = canvas.toDataURL("image/jpeg", 0.6);
    const dateText = document.getElementById(
      category === "rscc" ? "rsccDateDisplay" : "dynamicDateDisplay",
    ).innerText;

    fetch("send_email.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        image: base64Image,
        dateRange: dateText,
        recipient: targetEmail,
      }),
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) showToast(`Email sent to ${targetEmail}!`, "success");
        else showToast("Error: " + data.message, "danger");
      });
  });
}

function showToast(message, type = "info") {
  const toastEl = document.getElementById("liveToast");
  const toastMessage = document.getElementById("toastMessage");
  toastEl.className = `toast align-items-center text-white bg-${type} border-0`;
  toastMessage.innerText = message;
  new bootstrap.Toast(toastEl).show();
}

// Sidebar Upload toggle
const toggleBtn = document.getElementById("toggleUploadBtn");
const collapseContent = document.getElementById("uploadCollapseContent");
const toggleIcon = document.getElementById("toggleIcon");

if (toggleBtn) {
  toggleBtn.addEventListener("click", function () {
    const isHidden = collapseContent.style.display === "none";
    collapseContent.style.display = isHidden ? "block" : "none";
    toggleIcon.classList.toggle("rotate-icon", isHidden);
  });
}

// File Upload processing
document.getElementById("csvUpload").addEventListener("change", function (e) {
  const file = e.target.files[0];
  if (!file) return;

  const handleFileProcess = (data) => {
    if (!validateFormat(data)) {
      this.value = "";
      return;
    }
    tempUploadData = data;
    showToast("File ready for upload.", "success");
  };

  if (file.name.endsWith(".xlsx") || file.name.endsWith(".xls")) {
    const reader = new FileReader();
    reader.onload = (e) => {
      const workbook = XLSX.read(new Uint8Array(e.target.result), {
        type: "array",
      });
      handleFileProcess(
        XLSX.utils.sheet_to_json(workbook.Sheets[workbook.SheetNames[0]], {
          defval: null,
        }),
      );
    };
    reader.readAsArrayBuffer(file);
  } else {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      complete: (r) => handleFileProcess(r.data),
    });
  }
});
