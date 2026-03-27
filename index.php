<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Social Triage Report</title>
    <link rel="stylesheet" href="pr-css.css" />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css"
      rel="stylesheet"
    />
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css"
      rel="stylesheet"
    />
    <link
      rel="stylesheet"
      href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css"
    />
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.3.2/papaparse.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx/dist/xlsx.full.min.js"></script>
    <link
      rel="icon"
      href="https://eventsprguide.infinityfree.me/img/dashboard.png"
      type="image/svg+xml"
    />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.css"/>
    <script src="https://cdn.jsdelivr.net/npm/driver.js@1.0.1/dist/driver.js.iife.js"></script>
  </head>
  <body>
    <nav class="topbar">
      <div class="page-title">
        <h1 class="mb-0" style="font-size: 1.25rem">
          <strong>
            Social Triage Report 
            <button type="button" id="startTourBtn" style="background: none; border: none; color: inherit; padding: 0; cursor: pointer;">
              <i class="bi bi-question-circle"></i>
            </button>
          </strong>
        </h1>
      </div>
      <div class="sendBtn">
        <button type="button" id="sendPreviewBtn" class="btn btn-send">
          <i class="bi bi-send-fill me-2"></i>Send
        </button>
      </div>
    </nav>

    <form
      id="reviewForm"
      action="/submit_review.php"
      method="POST"
      enctype="multipart/form-data"
    >
      <aside class="sidebar">
        <div class="prInfo-div">
          <div class="sidebar-group mb-4">
  <label for="reportCategory" class="sidebar-label">Report Category</label>
  <div class="custom-input-wrapper">
    <div class="input-icon-box bg-primary">
      <i class="bi bi-layers text-white"></i>
    </div>
    <select class="form-select custom-select" id="reportCategory">
      <option value="aftercare" selected>After Care Service Report</option>
      <option value="rscc">RSCC Performance Report</option>
    </select>
  </div>
</div>
          <div class="sidebar-group mb-4">
            <label for="viewRegion" class="sidebar-label">Region</label>
            <div class="custom-input-wrapper">
              <div class="input-icon-box bg-primary">
                <i class="bi bi-geo-alt text-white"></i>
              </div>
              <select class="form-select custom-select" id="viewRegion" name="region">
                <option selected disabled>Select Region...</option>
                <option value="ASIA">ASIA</option>
                <option value="AMERICA">AMERICA</option>
                <option value="EMEA">EMEA</option>
              </select>
            </div>
          </div>
          <div class="sidebar-group mb-4">
  <label id="dateLabel" class="sidebar-label">Select Date Range</label>
  <div class="custom-input-wrapper">
    <div class="input-icon-box bg-primary">
      <i class="bi bi-calendar3 text-white"></i>
    </div>
    <input type="text" id="dateRangePicker" class="form-control custom-input" placeholder="Select dates...">
    <input type="hidden" id="startDate" placeholder="Select start date...">
    <input type="hidden" id="endDate" placeholder="Select end date...">
  </div>
</div>

          <div class="sidebar-group">
            <label class="sidebar-label">Comments & Feedback</label>
            <textarea
              id="message1"
              name="reviewer_message"
              class="form-control custom-textarea"
              rows="5"
              placeholder="Enter message..."
            ></textarea>
          </div>
            
          <button type="button" id="viewReportBtn" class="btn btn-outline-primary btn-sm w-100 mt-2">View Report</button>
          <hr class="sidebar-divider" />
        </div>
          
        <div class="sidebar-section px-4 p-3" style="background-color:#f2f2f2">
          <button 
            type="button" 
            id="toggleUploadBtn" 
            class="btn btn-outline-primary btn-sm w-100 d-flex justify-content-between align-items-center"
            style="border-style: dashed; font-weight: 600;"
          >
            <span><i class="bi bi-cloud-arrow-up me-2"></i>Upload New Data</span>
            <i class="bi bi-chevron-down toggle-icon" id="toggleIcon"></i>
          </button>

          <div id="uploadCollapseContent" style="display: none;">
            <div class="my-3">
              <label class="form-label small fw-bold">Select Region</label>
              <select id="uploadRegion" class="form-select form-select-sm">
                <option selected>Select Region...</option>
                <option value="ASIA">ASIA</option>
                <option value="AMERICA">AMERICA</option>
                <option value="EMEA">EMEA</option>
              </select>
            </div>
            
            <div class="mb-3">
              <label class="form-label small fw-bold">Select File</label>
              <input class="form-control form-control-sm" type="file" id="csvUpload" accept=".csv, .xlsx, .xls" />
            </div>

            <button type="button" id="dbSaveBtn" class="btn btn-primary btn-sm w-100">
              Save to Database
            </button>
            
            <div id="uploadStatus" class="mt-2 text-center" style="display: none;"></div>
          </div>
        </div>
      </aside>

      <main class="page-area">
        <div id="form-sections">
          <div id="afterCareView">
          <div class="report-container">
            <div style="padding-bottom:5px; margin-bottom: 10px;border-bottom:2px solid #071952">
              <h2 class="fw-bold" style="color:#071952;">Social Triage After-care Service</h2>
            </div>
            <div id="dynamicDateDisplay" class="fw-bold text-muted mb-3">
              January 1, 2026
            </div>

            <div class="performance-grid">
              <div class="metric-box">
                <div class="text-secondary fw-bold">RSCC Performance</div>
                <hr />
                <div class="small">Total Sent to SCC</div>
                <span id="totalSent" class="main-metric">0</span>
                <hr />
                <div class="small">Total Responded</div>
                <span id="totalResponded" class="main-metric">0</span>
                <div id="totalRespondedPct" class="sub-metric">(0%)</div>
                <hr />
                <div class="small">Total Closed</div>
                <span id="totalClosed" class="main-metric">0</span>
                <div id="totalClosedPct" class="sub-metric">(0%)</div>
                <hr />
                <div class="small">For Response</div>
                <span id="forResponse" class="main-metric">0</span>
                <div id="forResponsePct" class="sub-metric">(0%)</div>
              </div>

              <div class="d-flex flex-column gap-3">
                <div class="chart-card">
                  <div class="fw-bold mb-3 text-uppercase text-muted">
                    Performance by Area (Country)
                  </div>
                  <div id="areaChartContainer" class="bar-container"></div>
                </div>

                <div class="chart-card">
                  <div class="fw-bold mb-2 text-uppercase text-muted">
                    Performance by Customer Journey
                  </div>
                  <div
                    class="d-flex justify-content-between py-2 text-center"
                    id="journeyContainer"
                  >
                    <div class="journey-badge flex-fill">
                      <h3 id="count-retention">0</h3>
                      <small class="text-muted">Retention</small>
                    </div>
                    <div class="journey-badge flex-fill">
                      <h3 id="count-fans">0</h3>
                      <small class="text-muted">Fans</small>
                    </div>
                    <div class="journey-badge flex-fill">
                      <h3 id="count-usage">0</h3>
                      <small class="text-muted">Usage</small>
                    </div>
                    <div class="journey-badge flex-fill">
                      <h3 id="count-prospecting">0</h3>
                      <small class="text-muted">Prospecting</small>
                    </div>
                  </div>
                </div>

                <div class="chart-card">
                  <div class="fw-bold mb-3 text-uppercase text-muted">
                    Performance by Accounts/Handles
                  </div>
                  <div id="accountHandlesContainer" class="bar-container">
                    <p class="small text-muted text-center w-100">
                      Upload CSV/Excel to see account performance
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="bottom-row">
              <div class="metric-box">
                <div class="fw-bold mb-3 small text-uppercase text-muted">
                  Social Media Platforms
                </div>
                <div id="platformList"></div>
              </div>

              <div class="chart-card">
                <div class="fw-bold mb-3 small text-uppercase text-muted">
                  Performance by Message Type
                </div>
                <div class="d-flex align-items-center">
                  <div id="messageTypeContainer" 
                       style="width: 120px; height: 120px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 0 10px rgba(0,0,0,0.1);">
                  </div>
                  
                  <div id="typeLegend" class="ms-4 flex-grow-1">
                  </div>
                </div>
              </div>

              <div class="metric-box">
                <div class="fw-bold mb-3 small text-uppercase text-muted">
                  Message sentiments
                </div>
                <div class="text-start small mb-2">
                  <span id="label-positive">Positive (0)</span>
                  <div class="progress mt-1" style="height: 8px">
                    <div
                      id="bar-positive"
                      class="progress-bar bg-success"
                      style="width: 0%"
                    ></div>
                  </div>
                </div>
                <div class="text-start small mb-2">
                  <span id="label-negative">Negative (0)</span>
                  <div class="progress mt-1" style="height: 8px">
                    <div
                      id="bar-negative"
                      class="progress-bar bg-danger"
                      style="width: 0%"
                    ></div>
                  </div>
                </div>
                <div class="text-start small">
                  <span id="label-neutral">Neutral (0)</span>
                  <div class="progress mt-1" style="height: 8px">
                    <div
                      id="bar-neutral"
                      class="progress-bar bg-info"
                      style="width: 0%"
                    ></div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          </div>

<div id="rsccView" style="display: none;">
    <div class="report-container">
        <div style="padding-bottom:5px; margin-bottom: 10px; border-bottom:2px solid #071952">
            <h2 class="fw-bold" style="color:#071952;">Social Triage RSCC Performance Report</h2>
        </div>
        <div id="rsccDateDisplay" class="fw-bold text-muted mb-3">January - March 2026</div>
        
        <div class="row">
            <div class="col-12">
                <div class="chart-card mb-4">
                    <h5 class="fw-bold" style="color:#071952;">Total Sent to SCC</h5>
                    <div class="chart-container"><canvas id="chartSent"></canvas></div>
                </div>
            </div>

            <div class="col-12">
                <div class="chart-card mb-4">
                    <h5 class="fw-bold" style="color:#088395;">Total Responded to SCC</h5>
                    <div class="chart-container"><canvas id="chartResponded"></canvas></div>
                </div>
            </div>

            <div class="col-12">
                <div class="chart-card mb-4">
                    <h5 class="fw-bold" style="color:#27ae60;">Total Closed</h5>
                    <div class="chart-container"><canvas id="chartClosed"></canvas></div>
                </div>
            </div>

            <div class="col-12">
                <div class="chart-card mb-4">
                    <h5 class="fw-bold" style="color:#f39c12;">For Response</h5>
                    <div class="chart-container"><canvas id="chartForResponse"></canvas></div>
                </div>
            </div>
        </div>
    </div>
</div>
      </main>
    </form>
    <div class="position-fixed top-0 end-0 p-3" style="z-index: 1100">
      <div id="liveToast" class="toast align-items-center text-white bg-dark border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
          <div class="toast-body" id="toastMessage">
          </div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
      </div>
    </div>
    <div class="modal fade" id="emailModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">🚀 Send Report</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <label for="destinationEmail" class="form-label">Recipient Email Address</label>
            <input type="email" class="form-control" id="destinationEmail" placeholder="name@example.com" required>
            <div id="emailError" class="text-danger small mt-1" style="display:none;">Please enter a valid email.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="confirmSendBtn">Send Now</button>
          </div>
        </div>
      </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="script.js"></script>
  </body>
</html>
