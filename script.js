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
  const missing = REQUIRED_HEADERS.filter((h) => !actualHeaders.includes(h));

  if (missing.length > 0) {
    showToast(`Invalid Format! Missing columns: ${missing.join(", ")}`, "danger");
    return false;
  }
  return true;
}

let dashboardMetrics = null;
let tempUploadData = null;

document.addEventListener("DOMContentLoaded", function () {
  const dateDisplay = document.getElementById("dynamicDateDisplay");

  function updateHeader() {
    const start = new Date(document.getElementById("startDate").value);
    const end = new Date(document.getElementById("endDate").value);

    if (!isNaN(start) && !isNaN(end)) {
      const options = { month: "short", day: "numeric", year: "numeric" };
      const startText = start.toLocaleDateString("en-US", options);
      const endText = end.toLocaleDateString("en-US", options);
      dateDisplay.innerText = `${startText} - ${endText}`;
    }
  }

  const dateConfig = {
    dateFormat: "Y-m-d",
    onChange: updateHeader,
  };

  flatpickr("#startDate", dateConfig);
  flatpickr("#endDate", dateConfig);
  updateHeader();

  const saveBtn = document.getElementById("dbSaveBtn");
  if (saveBtn) saveBtn.addEventListener("click", saveToDatabase);

  const viewBtn = document.getElementById("viewReportBtn");
  if (viewBtn) viewBtn.addEventListener("click", fetchAndDisplayReport);

  // --- NEW: MODAL LISTENERS ---
  const sendPreviewBtn = document.getElementById("sendPreviewBtn");
  if (sendPreviewBtn) {
    sendPreviewBtn.addEventListener("click", function () {
      if (!dashboardMetrics) {
        return showToast("Please view a report first!", "warning");
      }
      const emailModalEl = document.getElementById('emailModal');
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
      const modalElement = document.getElementById('emailModal');
      const modalInstance = bootstrap.Modal.getInstance(modalElement);
      modalInstance.hide();

      sendDashboardEmail(emailValue);
    });
  }

  const driver = window.driver.js.driver;
  const tour = driver({
    showProgress: true,
    steps: [
      { element: ".prInfo-div", popover: { title: "📊 Filter Your View", description: 'Adjust the <b>Region</b> and <b>Date Range</b> here, then click "View Report" to update.', side: "right", align: "center" } },
      { element: ".sidebar-section", popover: { title: "📥 Data Management", description: "Use this section to upload your CSV or Excel files.", side: "right", align: "center" } },
      { element: ".report-container", popover: { title: "📋 Interactive Dashboard", description: "This is your real-time preview. All charts update instantly.", side: "right", align: "center" } },
      { element: "#sendPreviewBtn", popover: { title: "🚀 Share Your Report", description: "Capture the dashboard and send it via email.", side: "left", align: "center" } },
    ],
  });

  const startTourBtn = document.getElementById("startTourBtn");
  if (startTourBtn) {
    startTourBtn.addEventListener("click", () => { tour.drive(); });
  }

  if (!localStorage.getItem("dashboard_tour_seen")) {
    tour.drive();
    localStorage.setItem("dashboard_tour_seen", "true");
  }
});

function formatInboundDate(input) {
  if (!input) return null;
  if (typeof input === "number") {
    const date = new Date(Math.round((input - 25569) * 86400 * 1000));
    return date.toISOString().split("T")[0];
  }
  if (typeof input === "string") {
    const datePart = input.split(" ")[0];
    const standardizedDate = datePart.replace(/\//g, "-");
    const parts = standardizedDate.split("-");
    if (parts.length === 3) {
      let [p1, p2, p3] = parts;
      if (p1.length === 4) return `${p1}-${p2.padStart(2, "0")}-${p3.padStart(2, "0")}`;
      return `${p3}-${p1.padStart(2, "0")}-${p2.padStart(2, "0")}`;
    }
  }
  return input;
}

document.getElementById("csvUpload").addEventListener("change", function (e) {
  const file = e.target.files[0];
  if (!file) return;
  const fileName = file.name.toLowerCase();
  const statusDiv = document.getElementById("uploadStatus");

  const handleFileProcess = (data) => {
    if (!validateFormat(data)) {
      tempUploadData = null;
      this.value = "";
      return;
    }
    const cleanedData = data.map((row) => {
      if (row["Inbound Message Date"]) {
        row["Inbound Message Date"] = formatInboundDate(row["Inbound Message Date"]);
      }
      return row;
    });
    tempUploadData = cleanedData;
    statusDiv.style.display = "block";
    statusDiv.className = "mt-2 small fw-bold text-success text-center";
    statusDiv.innerHTML = `✔ Format Verified (${cleanedData.length} rows). Click 'Save to Database'.`;
  };

  if (fileName.endsWith(".xlsx") || fileName.endsWith(".xls")) {
    const reader = new FileReader();
    reader.onload = function (e) {
      try {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: "array" });
        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        const jsonData = XLSX.utils.sheet_to_json(firstSheet, { defval: null });
        handleFileProcess(jsonData);
      } catch (error) {
        if (error.message.includes("Encrypted")) {
          showToast("This file is password protected.", "danger");
        } else {
          showToast("Failed to read Excel file.", "danger");
        }
        document.getElementById("csvUpload").value = "";
      }
    };
    reader.readAsArrayBuffer(file);
  } else if (fileName.endsWith(".csv")) {
    Papa.parse(file, {
      header: true,
      skipEmptyLines: true,
      dynamicTyping: true,
      complete: function (results) {
        if (results.data && results.data.length > 0) {
          handleFileProcess(results.data);
        } else {
          showToast("CSV file appears to be empty.", "danger");
        }
      },
    });
  } else {
    showToast("Unsupported file format.", "danger");
  }
});

async function saveToDatabase() {
  const statusDiv = document.getElementById("uploadStatus");
  const uploadRegion = document.getElementById("uploadRegion").value;
  const date = document.getElementById("startDate").value;
  const fileInput = document.getElementById("csvUpload");

  if (!tempUploadData) return showToast("Please upload and process a file first!", "danger");
  if (uploadRegion === "Select Region...") return showToast("Please select a region", "warning");

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

    const text = await response.text();
    let result = JSON.parse(text);

    if (result.status === "success") {
      statusDiv.className = "mt-2 small fw-bold text-success text-center";
      statusDiv.innerHTML = `✔ ${result.message}`;
      showToast(result.message, "success");
      tempUploadData = null;
      if (fileInput) fileInput.value = "";
    } else {
      const errorMsg = result.message || "Unknown Error";
      statusDiv.className = "mt-2 small fw-bold text-danger text-center";
      statusDiv.innerHTML = `❌ ${errorMsg}`;
      showToast(errorMsg, "danger");
    }
  } catch (error) {
    statusDiv.innerHTML = `❌ ${error.message}`;
  }
}

async function fetchAndDisplayReport() {
  const region = document.getElementById("viewRegion").value;
  const start = document.getElementById("startDate").value;
  const end = document.getElementById("endDate").value;

  if (region === "Select Region...") return showToast("Please select a region to view!", "warning");

  try {
    const response = await fetch(`fetch_report.php?region=${region}&start=${start}&end=${end}`);
    const data = await response.json();
    if (!data || data.length === 0) {
      showToast("No data found for the selected filters.", "warning");
    } else {
      updateDashboard(data);
    }
  } catch (e) {
    showToast("Error fetching data from database.", "danger");
  }
}

function updateDashboard(data) {
  const cleanData = data.filter((row) => row["Inbound Count (SUM)"] != null);
  const total = cleanData.length;
  if (total === 0) return;

  const responded = cleanData.filter((row) => row["Routing Stage (in) (Message)"] === "Responded To").length;
  const nonActionable = cleanData.filter((row) => row["Routing Stage (in) (Message)"] === "Non-Actionable").length;
  const forResponse = cleanData.filter((row) => ["New", "For Response"].includes(row["Routing Stage (in) (Message)"])).length;

  document.getElementById("totalSent").innerText = total;
  document.getElementById("totalResponded").innerText = responded;
  document.getElementById("totalRespondedPct").innerText = `(${((responded / total) * 100).toFixed(1)}%)`;
  document.getElementById("totalClosed").innerText = nonActionable;
  document.getElementById("totalClosedPct").innerText = `(${((nonActionable / total) * 100).toFixed(1)}%)`;
  document.getElementById("forResponse").innerText = forResponse;
  document.getElementById("forResponsePct").innerText = `(${((forResponse / total) * 100).toFixed(1)}%)`;

  const getTop4AndOthers = (countsObj) => {
    const entries = Object.entries(countsObj).sort((a, b) => b[1] - a[1]);
    if (entries.length <= 4) return entries;
    const top4 = entries.slice(0, 4);
    const othersCount = entries.slice(4).reduce((sum, entry) => sum + entry[1], 0);
    top4.push(["Others", othersCount]);
    return top4;
  };

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
      const color = colors[index] || colors[4];
      areaContainer.insertAdjacentHTML("beforeend", `
        <div class="bar-wrapper">
          <div class="bar" style="height: ${displayHeight}%; background: ${color};" title="${area}: ${count}">
            <span style="font-size: 15px; position: absolute; text-align:center; width: 100%; top: 2.5px; color: #FFFFFF; font-weight: bold;">${count}</span>
          </div>
          <div class="small mt-1" style="font-size: 11px; font-weight: bold; white-space: nowrap;">${area}</div>
        </div>`);
    });
  }

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
      const color = colors[index] || colors[4];
      accountContainer.insertAdjacentHTML("beforeend", `
      <div class="d-flex align-items-center mb-2" style="gap: 12px;">
        <div style="min-width: 200px; display: flex; justify-content: space-between; align-items: center;">
          <span class="fw-bold" style="font-size: 14px; white-space: nowrap; overflow: hidden; max-width: 150px;">${name}</span>
          <span class="text-muted" style="font-size: 12px;">${pct}%</span>
        </div>
        <div class="flex-grow-1">
          <div class="progress" style="height: 12px; background-color: #e9ecef; border-radius: 10px;">
            <div class="progress-bar" style="width: ${pct}%; background-color: ${color}; border-radius: 10px;"></div>
          </div>
        </div>
        <div style="min-width: 25px; text-align: right; font-size: 12px; color: #888;">${count}</div>
      </div>`);
    });
  }

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
    platformList.innerHTML += `<div class="d-flex justify-content-between small border-bottom mb-1"><span>${name}</span><b>${count}</b></div>`;
  });

  const sentiments = calculateSentiments(cleanData);
  const updateBar = (id, count) => {
    const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
    document.getElementById(`label-${id}`).innerText = `${id.charAt(0).toUpperCase() + id.slice(1)} (${count})`;
    document.getElementById(`bar-${id}`).style.width = pct + "%";
  };
  updateBar("positive", sentiments.Positive);
  updateBar("negative", sentiments.Negative);
  updateBar("neutral", sentiments.Neutral);

  dashboardMetrics = { total, responded, nonActionable, forResponse, journey, sentiments, rawData: cleanData };

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

function sendDashboardEmail(targetEmail) {
  const element = document.querySelector(".report-container");
  if (!element) return showToast("Report area not found!", "danger");

  showToast("Capturing report... Please wait.", "info");

  html2canvas(element, {
    scale: 1,
    useCORS: true,
    backgroundColor: "#f5f6f8",
    logging: false
  }).then(canvas => {
    const base64Image = canvas.toDataURL("image/jpeg", 0.6);
    const dateText = document.getElementById("dynamicDateDisplay")?.innerText || "Latest Report";

    fetch("send_email.php", {
      method: "POST",
      headers: { "Content-Type": "application/json", "Accept": "application/json" },
      body: JSON.stringify({
        image: base64Image,
        dateRange: dateText,
        recipient: targetEmail
      })
    })
    .then(async response => {
      const rawText = await response.text();
      return JSON.parse(rawText);
    })
    .then(data => {
      if (data.success) {
        showToast(`Email sent successfully to ${targetEmail}!`, "success");
        document.getElementById("destinationEmail").value = "";
      } else {
        showToast("Server Error: " + data.message, "danger");
      }
    })
    .catch(err => {
      showToast("Connection failed.", "danger");
    });
  });
}

const toggleBtn = document.getElementById("toggleUploadBtn");
const collapseContent = document.getElementById("uploadCollapseContent");
const toggleIcon = document.getElementById("toggleIcon");

if (toggleBtn) {
  toggleBtn.addEventListener("click", function () {
    const isHidden = collapseContent.style.display === "none";
    collapseContent.style.display = isHidden ? "block" : "none";
    if (isHidden) toggleIcon.classList.add("rotate-icon");
    else toggleIcon.classList.remove("rotate-icon");
  });
}

function showToast(message, type = "info") {
  const toastEl = document.getElementById("liveToast");
  const toastMessage = document.getElementById("toastMessage");
  toastEl.classList.remove("bg-dark", "bg-success", "bg-danger", "bg-warning", "bg-info");
  toastEl.classList.add(`bg-${type}`);
  toastMessage.innerText = message;
  const toast = new bootstrap.Toast(toastEl);
  toast.show();
}
