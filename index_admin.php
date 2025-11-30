<?php
// Include necessary files
require 'super_auth.php';
require 'dbconn_productProfit.php';

// Ensure only super admin or admin can access
require_super_admin();

// Helper function to handle errors gracefully
function logError($message, $error = null) {
    error_log($message . ': ' . ($error ? $error->getMessage() : 'Unknown error'));
}

// Default to last 7 days instead of 30 for faster initial load
$default_days = 7;
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime("-$default_days days"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dr Ecomm Formula Dashboard</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">
    
    <!-- React and React DOM -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    
    <!-- Babel for JSX -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    
    <!-- Recharts for charts -->
    <script src="https://unpkg.com/recharts/umd/Recharts.min.js"></script>
    
    <style>
        /* Add custom styles here */
        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #1E3C72;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Add skeleton loading styles */
        .skeleton {
            animation: skeleton-loading 1s linear infinite alternate;
        }
        
        @keyframes skeleton-loading {
            0% { background-color: rgba(200, 200, 200, 0.1); }
            100% { background-color: rgba(200, 200, 200, 0.3); }
        }
        
        .skeleton-text {
            width: 100%;
            height: 12px;
            margin-bottom: 8px;
            border-radius: 2px;
        }
        
        .skeleton-card {
            width: 100%;
            height: 100px;
            margin-bottom: 16px;
            border-radius: 4px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Root element for React to render to -->
    <div id="root">
        <!-- Initial loading state -->
        <div class="flex items-center justify-center min-h-screen">
            <div class="loading-spinner"></div>
            <p class="ml-4 text-gray-600">Loading dashboard...</p>
        </div>
    </div>
    
    <!-- Main Dashboard Component -->
    <script type="text/babel">
        // Destructure React hooks and Recharts components
        const { useState, useEffect, useCallback } = React;
        const { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } = Recharts;
        
        // Main Dashboard Component
        const Dashboard = () => {
            // State for navigation and filters
            const [activeTab, setActiveTab] = useState('dashboard');
            const [startDate, setStartDate] = useState('<?php echo $start_date; ?>');
            const [endDate, setEndDate] = useState('<?php echo $end_date; ?>');
            const [selectedTeam, setSelectedTeam] = useState(0);
            const [comparisonPeriod, setComparisonPeriod] = useState('7');
            
            // State for storing fetched data with separate loading states
            const [stats, setStats] = useState(null);
            const [statsLoading, setStatsLoading] = useState(true);
            
            const [dailySales, setDailySales] = useState(null);
            const [chartsLoading, setChartsLoading] = useState(true);
            
            const [teamSales, setTeamSales] = useState(null);
            const [teamSalesLoading, setTeamSalesLoading] = useState(true);
            
            const [winningDna, setWinningDna] = useState(null);
            const [winningDnaLoading, setWinningDnaLoading] = useState(true);
            
            const [topProducts, setTopProducts] = useState(null);
            const [topProductsLoading, setTopProductsLoading] = useState(true);
            
            const [teams, setTeams] = useState([]);
            const [teamsLoading, setTeamsLoading] = useState(true);
            
            const [teamMetrics, setTeamMetrics] = useState(null);
            const [teamMetricsLoading, setTeamMetricsLoading] = useState(true);
            
            const [error, setError] = useState(null);
            
            // Pagination state
            const [pageSize, setPageSize] = useState(10);
            const [currentTeamPage, setCurrentTeamPage] = useState(1);
            const [currentWinningPage, setCurrentWinningPage] = useState(1);
            const [currentTopPage, setCurrentTopPage] = useState(1);
            
            // Debug timing
            const [loadTimes, setLoadTimes] = useState({});

            // Function to load teams (needed for both tabs)
            const loadTeams = useCallback(async () => {
                try {
                    setTeamsLoading(true);
                    const startTime = performance.now();
                    
                    const response = await fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_teams`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    const data = await response.json();
                    setTeams(data);
                    
                    const endTime = performance.now();
                    setLoadTimes(prev => ({...prev, teams: (endTime - startTime).toFixed(2) + 'ms'}));
                } catch (err) {
                    console.error("Error loading teams:", err);
                    setError("Failed to load teams. Please check your connection and try again.");
                } finally {
                    setTeamsLoading(false);
                }
            }, []);

            // Load teams when component mounts
            useEffect(() => {
                loadTeams();
            }, [loadTeams]);

            // Load dashboard sections progressively when tab is dashboard
            useEffect(() => {
                if (activeTab === 'dashboard') {
                    // Reset loading states
                    setStatsLoading(true);
                    setChartsLoading(true);
                    setTeamSalesLoading(true);
                    setWinningDnaLoading(true);
                    setTopProductsLoading(true);
                    
                    // Load sections in sequence
                    loadStats();
                }
            }, [activeTab]);
            
            // Load comparison data when tab is team_comparison
            useEffect(() => {
                if (activeTab === 'team_comparison') {
                    loadTeamComparison();
                }
            }, [activeTab]);

            // Function to load stats summary (first priority)
            const loadStats = async () => {
                try {
                    setStatsLoading(true);
                    const startTime = performance.now();
                    
                    const response = await fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_stats&start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    const data = await response.json();
                    setStats(data);
                    
                    const endTime = performance.now();
                    setLoadTimes(prev => ({...prev, stats: (endTime - startTime).toFixed(2) + 'ms'}));
                    
                    // After stats are loaded, load charts
                    loadCharts();
                } catch (err) {
                    console.error("Error loading stats:", err);
                    setError("Failed to load summary statistics.");
                } finally {
                    setStatsLoading(false);
                }
            };
            
            // Function to load charts data (second priority)
            const loadCharts = async () => {
                try {
                    setChartsLoading(true);
                    const startTime = performance.now();
                    
                    const response = await fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_charts&start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    const data = await response.json();
                    setDailySales(data);
                    
                    const endTime = performance.now();
                    setLoadTimes(prev => ({...prev, charts: (endTime - startTime).toFixed(2) + 'ms'}));
                    
                    // After charts are loaded, load team sales
                    loadTeamSales();
                } catch (err) {
                    console.error("Error loading charts data:", err);
                    setError("Failed to load charts data.");
                } finally {
                    setChartsLoading(false);
                }
            };
            
            // Function to load team sales data (third priority)
            const loadTeamSales = async () => {
                try {
                    setTeamSalesLoading(true);
                    const startTime = performance.now();
                    
                    const response = await fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_team_sales&start_date=${startDate}&end_date=${endDate}&page=${currentTeamPage}&page_size=${pageSize}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    const data = await response.json();
                    setTeamSales(data);
                    
                    const endTime = performance.now();
                    setLoadTimes(prev => ({...prev, teamSales: (endTime - startTime).toFixed(2) + 'ms'}));
                    
                    // After team sales are loaded, load winning DNA in parallel with top products
                    loadWinningDna();
                    loadTopProducts();
                } catch (err) {
                    console.error("Error loading team sales:", err);
                    setError("Failed to load team sales data.");
                } finally {
                    setTeamSalesLoading(false);
                }
            };
            
            // Function to load winning DNA data (fourth priority - can load in parallel)
            const loadWinningDna = async () => {
                try {
                    setWinningDnaLoading(true);
                    const startTime = performance.now();
                    
                    const response = await fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_winning_dna&start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}&page=${currentWinningPage}&page_size=${pageSize}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    const data = await response.json();
                    setWinningDna(data);
                    
                    const endTime = performance.now();
                    setLoadTimes(prev => ({...prev, winningDna: (endTime - startTime).toFixed(2) + 'ms'}));
                } catch (err) {
                    console.error("Error loading winning DNA:", err);
                    setError("Failed to load winning DNA data.");
                } finally {
                    setWinningDnaLoading(false);
                }
            };
            
            // Function to load top products data (fourth priority - can load in parallel)
            const loadTopProducts = async () => {
                try {
                    setTopProductsLoading(true);
                    const startTime = performance.now();
                    
                    const response = await fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_top_products&start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}&page=${currentTopPage}&page_size=${pageSize}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    const data = await response.json();
                    setTopProducts(data);
                    
                    const endTime = performance.now();
                    setLoadTimes(prev => ({...prev, topProducts: (endTime - startTime).toFixed(2) + 'ms'}));
                } catch (err) {
                    console.error("Error loading top products:", err);
                    setError("Failed to load top products data.");
                } finally {
                    setTopProductsLoading(false);
                }
            };
            
            // Function to load team comparison data
            const loadTeamComparison = async () => {
                try {
                    setTeamMetricsLoading(true);
                    const startTime = performance.now();
                    
                    const response = await fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_team_comparison&period=${comparisonPeriod}`);
                    if (!response.ok) {
                        throw new Error(`HTTP error! Status: ${response.status}`);
                    }
                    const data = await response.json();
                    setTeamMetrics(data);
                    
                    const endTime = performance.now();
                    setLoadTimes(prev => ({...prev, teamComparison: (endTime - startTime).toFixed(2) + 'ms'}));
                } catch (err) {
                    console.error("Error loading team comparison:", err);
                    setError("Failed to load team comparison data.");
                } finally {
                    setTeamMetricsLoading(false);
                }
            };

            // Apply dashboard filters
            const handleDashboardFilter = (e) => {
                e.preventDefault();
                // Reset pagination
                setCurrentTeamPage(1);
                setCurrentWinningPage(1);
                setCurrentTopPage(1);
                
                // Start progressive loading
                loadStats();
            };
            
            // Apply team comparison filters
            const handleComparisonFilter = () => {
                loadTeamComparison();
            };

            // Format currency
            const formatCurrency = (value) => {
                if (!value) return 'RM 0.00';
                return 'RM ' + parseFloat(value).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };

            // Sidebar Navigation Component
            const Sidebar = () => (
                <div className="bg-gradient-to-b from-blue-900 to-blue-700 text-white w-64 fixed top-0 left-0 h-screen shadow-lg overflow-y-auto">
                    <div className="p-5 text-center border-b border-blue-800">
                        <h2 className="text-xl font-semibold">Dr Ecomm</h2>
                    </div>
                    
                    <div className="flex items-center p-5 border-b border-blue-800">
                        <div className="bg-blue-800/30 w-10 h-10 rounded-full flex items-center justify-center mr-3">
                            <i className="fas fa-user-circle"></i>
                        </div>
                        <div>
                            <span className="text-sm font-medium">Admin User</span>
                            <span className="text-xs block opacity-80">Super Admin</span>
                        </div>
                    </div>
                    
                    <ul className="p-2">
                        <li className={`mb-1 ${activeTab === 'dashboard' ? 'bg-white/20 border-l-4 border-white' : ''}`}>
                            <button 
                                onClick={() => setActiveTab('dashboard')} 
                                className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all"
                            >
                                <i className="fas fa-tachometer-alt mr-3 w-5 text-center"></i>
                                <span>Executive Dashboard</span>
                            </button>
                        </li>
                        <li className={`mb-1 ${activeTab === 'team_comparison' ? 'bg-white/20 border-l-4 border-white' : ''}`}>
                            <button 
                                onClick={() => setActiveTab('team_comparison')} 
                                className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all"
                            >
                                <i className="fas fa-users mr-3 w-5 text-center"></i>
                                <span>Team Comparison</span>
                            </button>
                        </li>
                        <li className="mb-1">
                            <a href="winning_dna.php" className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
                                <i className="fa-solid fa-medal mr-3 w-5 text-center"></i>
                                <span>Winning DNA</span>
                            </a>
                        </li>
                        <li className="mb-1">
                            <a href="teams.php" className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
                                <i className="fas fa-users mr-3 w-5 text-center"></i>
                                <span>Teams</span>
                            </a>
                        </li>
                        <li className="mb-1">
                            <a href="all_products.php" className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
                                <i className="fas fa-boxes mr-3 w-5 text-center"></i>
                                <span>All Products</span>
                            </a>
                        </li>
                        <li className="mb-1">
                            <a href="commission_calculator.php" className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
                                <i className="fas fa-calculator mr-3 w-5 text-center"></i>
                                <span>Commission Calculator</span>
                            </a>
                        </li>
                        <li className="mb-1">
                            <a href="admin_reports.php" className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
                                <i className="fas fa-file-download mr-3 w-5 text-center"></i>
                                <span>Reports</span>
                            </a>
                        </li>
                        <li>
                            <a href="logout.php" className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
                                <i className="fas fa-sign-out-alt mr-3 w-5 text-center"></i>
                                <span>Logout</span>
                            </a>
                        </li>
                    </ul>
                </div>
            );

            // Loading indicator component
            const SectionLoading = ({ height = "16" }) => (
                <div className={`flex justify-center items-center h-${height}`}>
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-800"></div>
                </div>
            );
            
            // Skeleton loaders for different components
            const StatCardSkeleton = () => (
                <div className="bg-white p-5 rounded-lg shadow-md">
                    <div className="skeleton w-12 h-12 rounded-full mb-4"></div>
                    <div className="skeleton-text skeleton w-1/2"></div>
                    <div className="skeleton-text skeleton w-3/4 h-8 my-2"></div>
                    <div className="skeleton-text skeleton w-2/3"></div>
                </div>
            );
            
            const ChartSkeleton = () => (
                <div className="bg-white p-6 rounded-lg shadow-md">
                    <div className="skeleton-text skeleton w-1/4 mb-4"></div>
                    <div className="skeleton h-80 w-full rounded"></div>
                </div>
            );
            
            const TableSkeleton = ({ rows = 5 }) => (
                <div className="bg-white rounded-lg shadow-md overflow-hidden">
                    <div className="flex justify-between items-center bg-gray-50 p-4 border-b border-gray-200">
                        <div className="skeleton-text skeleton w-1/4"></div>
                        <div className="skeleton w-24 h-8 rounded"></div>
                    </div>
                    <div className="p-4">
                        <div className="skeleton w-full h-12 rounded mb-4"></div>
                        {[...Array(rows)].map((_, i) => (
                            <div key={i} className="skeleton w-full h-10 rounded mb-2"></div>
                        ))}
                    </div>
                </div>
            );

            // Error message component
            const ErrorMessage = ({ message }) => (
                <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
                    <strong className="font-bold">Error: </strong>
                    <span className="block sm:inline">{message}</span>
                </div>
            );

            // Debug info component
            const DebugInfo = () => (
                <div className="fixed bottom-0 right-0 bg-black/70 text-white p-2 text-xs rounded-tl-md max-w-xs">
                    <div className="font-bold mb-1">Loading times:</div>
                    {Object.entries(loadTimes).map(([key, value]) => (
                        <div key={key}>{key}: {value}</div>
                    ))}
                </div>
            );

            // Stat Card Component
            const StatCard = ({ icon, iconClass, title, value, change }) => (
                <div className="bg-white p-5 rounded-lg shadow-md">
                    <div className={`w-12 h-12 rounded-full flex items-center justify-center mb-4 ${iconClass}`}>
                        <i className={`${icon} text-lg`}></i>
                    </div>
                    <p className="text-sm text-gray-600">{title}</p>
                    <h3 className="text-2xl font-bold text-blue-900 my-2">{value}</h3>
                    <p className={`text-sm flex items-center ${change?.includes('+') ? 'text-green-600' : change?.includes('-') ? 'text-red-600' : 'text-gray-600'}`}>
                        {change && <i className={`mr-1 fas fa-${change?.includes('+') ? 'arrow-up' : change?.includes('-') ? 'arrow-down' : 'calendar'}`}></i>}
                        {change}
                    </p>
                </div>
            );
            
            // Pagination component
            const Pagination = ({ currentPage, totalPages, onPageChange }) => (
                <div className="flex justify-center space-x-2 mt-4">
                    <button 
                        onClick={() => onPageChange(currentPage - 1)} 
                        disabled={currentPage === 1}
                        className={`px-3 py-1 rounded ${currentPage === 1 ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-blue-800 text-white hover:bg-blue-700'}`}
                    >
                        <i className="fas fa-chevron-left"></i>
                    </button>
                    
                    <span className="px-3 py-1 bg-gray-100 rounded">
                        {currentPage} of {totalPages}
                    </span>
                    
                    <button 
                        onClick={() => onPageChange(currentPage + 1)} 
                        disabled={currentPage === totalPages}
                        className={`px-3 py-1 rounded ${currentPage === totalPages ? 'bg-gray-200 text-gray-500 cursor-not-allowed' : 'bg-blue-800 text-white hover:bg-blue-700'}`}
                    >
                        <i className="fas fa-chevron-right"></i>
                    </button>
                </div>
            );

            // Dashboard Content
            const DashboardContent = () => {
                // Check if any error occurred
                if (error) return <ErrorMessage message={error} />;

                return (
                    <div>
                        {/* Page Header & Date Filter */}
                        <div className="flex justify-between items-center mb-5 pb-4 border-b border-gray-200 flex-wrap">
                            <h1 className="text-2xl font-semibold text-blue-900">Executive Dashboard</h1>
                            <div className="bg-white rounded-lg p-3 shadow-md flex items-center">
                                <form onSubmit={handleDashboardFilter} className="flex flex-wrap items-center">
                                    <label htmlFor="start_date" className="mr-2">From:</label>
                                    <input 
                                        type="date" 
                                        id="start_date" 
                                        value={startDate} 
                                        onChange={(e) => setStartDate(e.target.value)} 
                                        className="border border-gray-300 rounded px-3 py-2 mr-3"
                                    />
                                    
                                    <label htmlFor="end_date" className="mr-2">To:</label>
                                    <input 
                                        type="date" 
                                        id="end_date" 
                                        value={endDate} 
                                        onChange={(e) => setEndDate(e.target.value)} 
                                        className="border border-gray-300 rounded px-3 py-2 mr-3"
                                    />
                                    
                                    <label htmlFor="team_id" className="mr-2">Team:</label>
                                    <select 
                                        id="team_id" 
                                        value={selectedTeam} 
                                        onChange={(e) => setSelectedTeam(Number(e.target.value))} 
                                        className="border border-gray-300 rounded px-3 py-2 mr-3"
                                    >
                                        <option value={0}>All Teams</option>
                                        {teams.map(team => (
                                            <option key={team.team_id} value={team.team_id}>{team.team_name}</option>
                                        ))}
                                    </select>
                                    
                                    <select
                                        value={pageSize}
                                        onChange={(e) => setPageSize(Number(e.target.value))}
                                        className="border border-gray-300 rounded px-3 py-2 mr-3"
                                    >
                                        <option value={5}>5 rows</option>
                                        <option value={10}>10 rows</option>
                                        <option value={20}>20 rows</option>
                                        <option value={50}>50 rows</option>
                                    </select>
                                    
                                    <button 
                                        type="submit" 
                                        className="bg-blue-800 text-white px-4 py-2 rounded hover:bg-blue-700"
                                    >
                                        Filter
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        {/* Stats Overview */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
                            {statsLoading ? (
                                <>
                                    <StatCardSkeleton />
                                    <StatCardSkeleton />
                                    <StatCardSkeleton />
                                    <StatCardSkeleton />
                                    <StatCardSkeleton />
                                    <StatCardSkeleton />
                                    <StatCardSkeleton />
                                </>
                            ) : stats ? (
                                <>
                                    <StatCard 
                                        icon="fas fa-dollar-sign" 
                                        iconClass="bg-blue-100 text-blue-500" 
                                        title="Total Sales" 
                                        value={formatCurrency(stats.total_sales)} 
                                        change={`Period: ${startDate.substring(5)} - ${endDate.substring(5)}`}
                                    />
                                    <StatCard 
                                        icon="fas fa-chart-line" 
                                        iconClass="bg-green-100 text-green-500" 
                                        title="Total Profit" 
                                        value={formatCurrency(stats.total_profit)} 
                                        change={`${((stats.total_profit / stats.total_sales) * 100).toFixed(1)}% margin`}
                                    />
                                    <StatCard 
                                        icon="fas fa-shopping-cart" 
                                        iconClass="bg-orange-100 text-orange-500" 
                                        title="Total Orders" 
                                        value={parseInt(stats.total_orders).toLocaleString()} 
                                        change={`Units: ${parseInt(stats.total_units).toLocaleString()}`}
                                    />
                                    <StatCard 
                                        icon="fas fa-ad" 
                                        iconClass="bg-red-100 text-red-500" 
                                        title="Total Ads Spend" 
                                        value={formatCurrency(stats.total_ads_spend)} 
                                        change={`ROI: ${((stats.total_profit / stats.total_ads_spend) * 100).toFixed(1)}%`}
                                    />
                                    <StatCard 
                                        icon="fas fa-box-open" 
                                        iconClass="bg-gray-100 text-gray-600" 
                                        title="Total COGS" 
                                        value={formatCurrency(stats.total_cogs)} 
                                        change={`${((stats.total_cogs / stats.total_sales) * 100).toFixed(1)}% of revenue`}
                                    />
                                    <StatCard 
                                        icon="fas fa-truck" 
                                        iconClass="bg-yellow-100 text-yellow-600" 
                                        title="Total Shipping" 
                                        value={formatCurrency(stats.total_shipping)} 
                                        change={`${((stats.total_shipping / stats.total_sales) * 100).toFixed(1)}% of revenue`}
                                    />
                                    <StatCard 
                                        icon="fas fa-box" 
                                        iconClass="bg-purple-100 text-purple-600" 
                                        title="Products" 
                                        value={parseInt(stats.unique_products).toLocaleString()} 
                                        change={`${Math.round((new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24))} days`}
                                    />
                                </>
                            ) : (
                                <div className="col-span-4">No data available</div>
                            )}
                        </div>
                        
                        {/* Charts */}
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                            {chartsLoading ? (
                                <>
                                    <ChartSkeleton />
                                    <ChartSkeleton />
                                </>
                            ) : dailySales && dailySales.length > 0 ? (
                                <>
                                    <div className="bg-white p-6 rounded-lg shadow-md">
                                        <h3 className="text-lg font-semibold mb-4">Sales & Profit Trend</h3>
                                        <div className="h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <LineChart data={dailySales}>
                                                    <CartesianGrid strokeDasharray="3 3" />
                                                    <XAxis dataKey="sale_date" />
                                                    <YAxis />
                                                    <Tooltip 
                                                        formatter={(value) => ['RM ' + parseFloat(value).toLocaleString()]} 
                                                        labelFormatter={(label) => `Date: ${label}`}
                                                    />
                                                    <Legend />
                                                    <Line type="monotone" dataKey="daily_sales" name="Sales (RM)" stroke="#36A2EB" activeDot={{ r: 8 }} strokeWidth={2} />
                                                    <Line type="monotone" dataKey="daily_profit" name="Profit (RM)" stroke="#4BC0C0" strokeWidth={2} />
                                                </LineChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>
                                    
                                    <div className="bg-white p-6 rounded-lg shadow-md">
                                        <h3 className="text-lg font-semibold mb-4">Revenue vs Expenses</h3>
                                        <div className="h-80">
                                            <ResponsiveContainer width="100%" height="100%">
                                                <LineChart data={dailySales}>
                                                    <CartesianGrid strokeDasharray="3 3" />
                                                    <XAxis dataKey="sale_date" />
                                                    <YAxis />
                                                    <Tooltip 
                                                        formatter={(value) => ['RM ' + parseFloat(value).toLocaleString()]} 
                                                        labelFormatter={(label) => `Date: ${label}`}
                                                    />
                                                    <Legend />
                                                    <Line type="monotone" dataKey="daily_sales" name="Revenue (RM)" stroke="#36A2EB" activeDot={{ r: 8 }} strokeWidth={2} />
                                                    <Line type="monotone" dataKey="daily_ads_spend" name="Ads Spend (RM)" stroke="#FF6384" strokeWidth={2} />
                                                    <Line type="monotone" dataKey="daily_cogs" name="COGS (RM)" stroke="#636C78" strokeWidth={2} />
                                                </LineChart>
                                            </ResponsiveContainer>
                                        </div>
                                    </div>
                                </>
                            ) : (
                                <div className="col-span-2">
                                    <div className="bg-white p-6 rounded-lg shadow-md text-center">
                                        <p className="text-gray-500">No chart data available for the selected date range.</p>
                                    </div>
                                </div>
                            )}
                        </div>
                        
                        {/* Team Performance Table */}
                        <div className="bg-white rounded-lg shadow-md mb-8 overflow-hidden">
                            <div className="flex justify-between items-center bg-gray-50 p-4 border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-blue-900">Team Performance</h3>
                                <a 
                                    href={`team_performance_export.php?start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`}
                                    className="bg-blue-800 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700"
                                >
                                    <i className="fas fa-download mr-1"></i> Export
                                </a>
                            </div>
                            {teamSalesLoading ? (
                                <div className="p-4">
                                    <SectionLoading />
                                </div>
                            ) : teamSales && teamSales.data && teamSales.data.length > 0 ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="bg-blue-800 text-white">
                                                    <th className="text-left p-3">Team</th>
                                                    <th className="text-left p-3">Total Sales (RM)</th>
                                                    <th className="text-left p-3">Total Profit (RM)</th>
                                                    <th className="text-left p-3">Margin %</th>
                                                    <th className="text-left p-3">Ads Spend (RM)</th>
                                                    <th className="text-left p-3">ROI %</th>
                                                    <th className="text-left p-3">COGS (RM)</th>
                                                    <th className="text-left p-3">Shipping (RM)</th>
                                                    <th className="text-left p-3">Orders</th>
                                                    <th className="text-left p-3">Units Sold</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {teamSales.data.map((team) => (
                                                    <tr key={team.team_id} className="border-b border-gray-200 hover:bg-gray-50">
                                                        <td className="p-3">{team.team_name}</td>
                                                        <td className="p-3">{formatCurrency(team.team_sales)}</td>
                                                        <td className="p-3">{formatCurrency(team.team_profit)}</td>
                                                        <td className="p-3">{parseFloat(team.profit_margin).toFixed(1)}%</td>
                                                        <td className="p-3">{formatCurrency(team.team_ads_spend)}</td>
                                                        <td className="p-3">{parseFloat(team.roi).toFixed(1)}%</td>
                                                        <td className="p-3">{formatCurrency(team.team_cogs)}</td>
                                                        <td className="p-3">{formatCurrency(team.team_shipping)}</td>
                                                        <td className="p-3">{parseInt(team.orders_count).toLocaleString()}</td>
                                                        <td className="p-3">{parseInt(team.units_sold).toLocaleString()}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {teamSales.total_pages > 1 && (
                                        <Pagination 
                                            currentPage={currentTeamPage}
                                            totalPages={teamSales.total_pages}
                                            onPageChange={(page) => {
                                                setCurrentTeamPage(page);
                                                setTeamSalesLoading(true);
                                                fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_team_sales&start_date=${startDate}&end_date=${endDate}&page=${page}&page_size=${pageSize}`)
                                                    .then(res => res.json())
                                                    .then(data => {
                                                        setTeamSales(data);
                                                        setTeamSalesLoading(false);
                                                    })
                                                    .catch(err => {
                                                        console.error(err);
                                                        setError("Failed to load team data page");
                                                        setTeamSalesLoading(false);
                                                    });
                                            }}
                                        />
                                    )}
                                </>
                            ) : (
                                <div className="p-4 text-center text-gray-500">
                                    No team performance data available.
                                </div>
                            )}
                        </div>
                        
                        {/* Winning DNA Table */}
                        <div className="bg-white rounded-lg shadow-md mb-8 overflow-hidden">
                            <div className="flex justify-between items-center bg-gray-50 p-4 border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-blue-900">Winning DNA (Top ROI Products)</h3>
                                <a 
                                    href={`winning_dna_export.php?start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`}
                                    className="bg-blue-800 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700"
                                >
                                    <i className="fas fa-download mr-1"></i> Export
                                </a>
                            </div>
                            {winningDnaLoading ? (
                                <div className="p-4">
                                    <SectionLoading />
                                </div>
                            ) : winningDna && winningDna.data && winningDna.data.length > 0 ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="bg-blue-800 text-white">
                                                    <th className="text-left p-3">Product</th>
                                                    <th className="text-left p-3">Sales (RM)</th>
                                                    <th className="text-left p-3">Profit (RM)</th>
                                                    <th className="text-left p-3">Ads Spend (RM)</th>
                                                    <th className="text-left p-3">ROI %</th>
                                                    <th className="text-left p-3">Margin %</th>
                                                    <th className="text-left p-3">Units Sold</th>
                                                    <th className="text-left p-3">Team</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {winningDna.data.map((product, index) => (
                                                    <tr key={index} className="border-b border-gray-200 hover:bg-gray-50">
                                                        <td className="p-3">{product.product_name}</td>
                                                        <td className="p-3">{formatCurrency(product.total_sales)}</td>
                                                        <td className="p-3">{formatCurrency(product.total_profit)}</td>
                                                        <td className="p-3">{formatCurrency(product.total_ads_spend)}</td>
                                                        <td className="p-3">{parseFloat(product.roi).toFixed(1)}%</td>
                                                        <td className="p-3">{parseFloat(product.profit_margin).toFixed(1)}%</td>
                                                        <td className="p-3">{parseInt(product.total_sold).toLocaleString()}</td>
                                                        <td className="p-3">
                                                            {teams.find(team => team.team_id == product.team_id)?.team_name || 'Unknown'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {winningDna.total_pages > 1 && (
                                        <Pagination 
                                            currentPage={currentWinningPage}
                                            totalPages={winningDna.total_pages}
                                            onPageChange={(page) => {
                                                setCurrentWinningPage(page);
                                                setWinningDnaLoading(true);
                                                fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_winning_dna&start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}&page=${page}&page_size=${pageSize}`)
                                                    .then(res => res.json())
                                                    .then(data => {
                                                        setWinningDna(data);
                                                        setWinningDnaLoading(false);
                                                    })
                                                    .catch(err => {
                                                        console.error(err);
                                                        setError("Failed to load winning DNA page");
                                                        setWinningDnaLoading(false);
                                                    });
                                            }}
                                        />
                                    )}
                                </>
                            ) : (
                                <div className="p-4 text-center text-gray-500">
                                    No winning DNA data available.
                                </div>
                            )}
                        </div>
                        
                        {/* Top Products Table */}
                        <div className="bg-white rounded-lg shadow-md overflow-hidden">
                            <div className="flex justify-between items-center bg-gray-50 p-4 border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-blue-900">Top Profitable Products</h3>
                                <a 
                                    href={`top_products_export.php?start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`}
                                    className="bg-blue-800 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700"
                                >
                                    <i className="fas fa-download mr-1"></i> Export
                                </a>
                            </div>
                            {topProductsLoading ? (
                                <div className="p-4">
                                    <SectionLoading />
                                </div>
                            ) : topProducts && topProducts.data && topProducts.data.length > 0 ? (
                                <>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="bg-blue-800 text-white">
                                                    <th className="text-left p-3">Product</th>
                                                    <th className="text-left p-3">Units Sold</th>
                                                    <th className="text-left p-3">Total Sales (RM)</th>
                                                    <th className="text-left p-3">Total Profit (RM)</th>
                                                    <th className="text-left p-3">Ads Spend (RM)</th>
                                                    <th className="text-left p-3">COGS (RM)</th>
                                                    <th className="text-left p-3">Profit Margin</th>
                                                    <th className="text-left p-3">Team</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {topProducts.data.map((product, index) => (
                                                    <tr key={index} className="border-b border-gray-200 hover:bg-gray-50">
                                                        <td className="p-3">{product.product_name}</td>
                                                        <td className="p-3">{parseInt(product.total_sold).toLocaleString()}</td>
                                                        <td className="p-3">{formatCurrency(product.total_sales)}</td>
                                                        <td className="p-3">{formatCurrency(product.total_profit)}</td>
                                                        <td className="p-3">{formatCurrency(product.ads_spend)}</td>
                                                        <td className="p-3">{formatCurrency(product.cogs)}</td>
                                                        <td className="p-3">{parseFloat(product.profit_margin).toFixed(1)}%</td>
                                                        <td className="p-3">
                                                            {teams.find(team => team.team_id == product.team_id)?.team_name || 'Unknown'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                    {topProducts.total_pages > 1 && (
                                        <Pagination 
                                            currentPage={currentTopPage}
                                            totalPages={topProducts.total_pages}
                                            onPageChange={(page) => {
                                                setCurrentTopPage(page);
                                                setTopProductsLoading(true);
                                                fetch(`<?php echo $_SERVER['PHP_SELF']; ?>?action=get_top_products&start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}&page=${page}&page_size=${pageSize}`)
                                                    .then(res => res.json())
                                                    .then(data => {
                                                        setTopProducts(data);
                                                        setTopProductsLoading(false);
                                                    })
                                                    .catch(err => {
                                                        console.error(err);
                                                        setError("Failed to load top products page");
                                                        setTopProductsLoading(false);
                                                    });
                                            }}
                                        />
                                    )}
                                </>
                            ) : (
                                <div className="p-4 text-center text-gray-500">
                                    No top products data available.
                                </div>
                            )}
                        </div>
                    </div>
                );
            };
            
            // Team Comparison Content
            const TeamComparisonContent = () => {
                // Check if any error occurred
                if (error) return <ErrorMessage message={error} />;
                
                // If data is still loading, show skeleton loaders
                if (teamMetricsLoading) {
                    return (
                        <div>
                            <div className="flex justify-between items-center mb-5 pb-4 border-b border-gray-200">
                                <h1 className="text-2xl font-semibold text-blue-900">Team Performance Comparison</h1>
                                <div className="flex items-center">
                                    <div className="mr-4">
                                        <select className="border border-gray-300 rounded p-2 pr-8">
                                            <option>Last 7 Days</option>
                                            <option>Last 30 Days</option>
                                            <option>Last 60 Days</option>
                                            <option>Last 90 Days</option>
                                        </select>
                                    </div>
                                    <div className="skeleton w-32 h-10 rounded"></div>
                                </div>
                            </div>
                            
                            <ChartSkeleton />
                            
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                                <StatCardSkeleton />
                                <StatCardSkeleton />
                                <StatCardSkeleton />
                                <StatCardSkeleton />
                            </div>
                            
                            <TableSkeleton rows={5} />
                        </div>
                    );
                }
                
                // If no data is available
                if (!teamMetrics || !teamMetrics.team_metrics || teamMetrics.team_metrics.length === 0) {
                    return (
                        <div>
                            <div className="flex justify-between items-center mb-5 pb-4 border-b border-gray-200">
                                <h1 className="text-2xl font-semibold text-blue-900">Team Performance Comparison</h1>
                                <div className="flex items-center">
                                    <div className="mr-4">
                                        <select 
                                            className="border border-gray-300 rounded p-2 pr-8"
                                            value={comparisonPeriod}
                                            onChange={(e) => {
                                                setComparisonPeriod(e.target.value);
                                                setTimeout(handleComparisonFilter, 0);
                                            }}
                                        >
                                            <option value="7">Last 7 Days</option>
                                            <option value="30">Last 30 Days</option>
                                            <option value="60">Last 60 Days</option>
                                            <option value="90">Last 90 Days</option>
                                            <option value="365">This Year</option>
                                        </select>
                                    </div>
                                    <button 
                                        onClick={handleComparisonFilter}
                                        className="bg-blue-800 text-white px-4 py-2 rounded"
                                    >
                                        Compare Teams
                                    </button>
                                </div>
                            </div>
                            <div className="bg-white p-6 rounded-lg shadow-md text-center">
                                <p className="text-gray-500">No team comparison data available.</p>
                            </div>
                        </div>
                    );
                }
                
                // Filter out teams with no sales for the chart
                const activeTeams = teamMetrics.team_metrics.filter(team => parseFloat(team.team_sales) > 0);
                
                return (
                    <div>
                        <div className="flex justify-between items-center mb-5 pb-4 border-b border-gray-200">
                            <h1 className="text-2xl font-semibold text-blue-900">Team Performance Comparison</h1>
                            <div className="flex items-center">
                                <div className="mr-4">
                                    <select 
                                        className="border border-gray-300 rounded p-2 pr-8"
                                        value={comparisonPeriod}
                                        onChange={(e) => {
                                            setComparisonPeriod(e.target.value);
                                            setTimeout(handleComparisonFilter, 0);
                                        }}
                                    >
                                        <option value="7">Last 7 Days</option>
                                        <option value="30">Last 30 Days</option>
                                        <option value="60">Last 60 Days</option>
                                        <option value="90">Last 90 Days</option>
                                        <option value="365">This Year</option>
                                    </select>
                                </div>
                                <button 
                                    onClick={handleComparisonFilter}
                                    className="bg-blue-800 text-white px-4 py-2 rounded"
                                >
                                    Compare Teams
                                </button>
                            </div>
                        </div>
                        
                        {/* Team Comparison Chart */}
                        <div className="bg-white p-6 rounded-lg shadow-md mb-8">
                            <h3 className="text-lg font-semibold mb-4">Team Performance Comparison (Last {comparisonPeriod} Days)</h3>
                            <div className="h-96">
                                <ResponsiveContainer width="100%" height="100%">
                                    <BarChart data={activeTeams}>
                                        <CartesianGrid strokeDasharray="3 3" />
                                        <XAxis dataKey="team_name" />
                                        <YAxis />
                                        <Tooltip 
                                            formatter={(value) => ['RM ' + parseFloat(value).toLocaleString()]} 
                                        />
                                        <Legend />
                                        <Bar dataKey="team_sales" name="Total Sales (RM)" fill="#36A2EB" />
                                        <Bar dataKey="team_profit" name="Total Profit (RM)" fill="#4BC0C0" />
                                    </BarChart>
                                </ResponsiveContainer>
                            </div>
                        </div>
                        
                        {/* Team Cards */}
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            {teamMetrics.team_metrics.map(team => (
                                <div key={team.team_id} className="bg-white rounded-lg shadow-md overflow-hidden">
                                    <div className="p-4 border-b border-gray-200">
                                        <h3 className="text-lg font-semibold text-blue-900">{team.team_name}</h3>
                                    </div>
                                    <div className="p-4">
                                        <div className="grid grid-cols-2 gap-4 mb-4">
                                            <div className="bg-gray-50 p-4 rounded">
                                                <div className="text-xl font-bold text-blue-900">{formatCurrency(team.team_sales)}</div>
                                                <div className="text-sm text-gray-600">Total Sales</div>
                                            </div>
                                            <div className="bg-gray-50 p-4 rounded">
                                                <div className="text-xl font-bold text-blue-900">{formatCurrency(team.team_profit)}</div>
                                                <div className="text-sm text-gray-600">Total Profit</div>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4 mb-4">
                                            <div className="bg-gray-50 p-4 rounded">
                                                <div className="text-xl font-bold text-blue-900">{team.product_count}</div>
                                                <div className="text-sm text-gray-600">Products</div>
                                            </div>
                                            <div className="bg-gray-50 p-4 rounded">
                                                <div className="text-xl font-bold text-blue-900">{parseFloat(team.profit_margin).toFixed(1)}%</div>
                                                <div className="text-sm text-gray-600">Profit Margin</div>
                                            </div>
                                        </div>
                                        <div className="bg-gray-50 p-4 rounded">
                                            <div className={`text-xl font-bold ${team.growth < 0 ? 'text-red-600' : team.growth > 0 ? 'text-green-600' : 'text-gray-600'}`}>
                                                {team.growth > 0 ? '+' : ''}{parseFloat(team.growth).toFixed(1)}%
                                            </div>
                                            <div className="text-sm text-gray-600">Growth</div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                        
                        {/* Detailed Metrics Table */}
                        <div className="bg-white rounded-lg shadow-md overflow-hidden">
                            <div className="flex justify-between items-center bg-gray-50 p-4 border-b border-gray-200">
                                <h3 className="text-lg font-semibold text-blue-900">Detailed Team Metrics</h3>
                                <a 
                                    href={`team_performance_export.php?period=${comparisonPeriod}`}
                                    className="bg-blue-800 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700"
                                >
                                    <i className="fas fa-download mr-1"></i> Export
                                </a>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full">
                                    <thead>
                                        <tr className="bg-blue-800 text-white">
                                            <th className="text-left p-3">Team</th>
                                            <th className="text-left p-3">Total Sales (RM)</th>
                                            <th className="text-left p-3">Total Profit (RM)</th>
                                            <th className="text-left p-3">Margin %</th>
                                            <th className="text-left p-3">Ads Spend (RM)</th>
                                            <th className="text-left p-3">ROI %</th>
                                            <th className="text-left p-3">COGS (RM)</th>
                                            <th className="text-left p-3">Products</th>
                                            <th className="text-left p-3">Growth %</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {teamMetrics.team_metrics.map((team) => (
                                            <tr key={team.team_id} className="border-b border-gray-200 hover:bg-gray-50">
                                                <td className="p-3">{team.team_name}</td>
                                                <td className="p-3">{formatCurrency(team.team_sales)}</td>
                                                <td className="p-3">{formatCurrency(team.team_profit)}</td>
                                                <td className="p-3">{parseFloat(team.profit_margin).toFixed(1)}%</td>
                                                <td className="p-3">{formatCurrency(team.team_ads_spend)}</td>
                                                <td className="p-3">{parseFloat(team.roi).toFixed(1)}%</td>
                                                <td className="p-3">{formatCurrency(team.team_cogs)}</td>
                                                <td className="p-3">{team.product_count}</td>
                                                <td className={`p-3 ${team.growth < 0 ? 'text-red-600' : team.growth > 0 ? 'text-green-600' : 'text-gray-600'}`}>
                                                    {team.growth > 0 ? '+' : ''}{parseFloat(team.growth).toFixed(1)}%
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                );
            };
            
            // Main component rendering
            return (
                <div className="flex min-h-screen bg-gray-100">
                    <Sidebar />
                    <div className="ml-64 w-full p-6">
                        {activeTab === 'dashboard' ? <DashboardContent /> : <TeamComparisonContent />}
                        {/* Uncomment the line below to show loading times for debugging */}
                        {/* <DebugInfo /> */}
                    </div>
                </div>
            );
        };
        
        // Render Dashboard to root element
        ReactDOM.createRoot(document.getElementById('root')).render(<Dashboard />);
    </script>
</body>
</html>

<?php
// API Endpoint Handler for AJAX calls
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Get action type
    $action = $_GET['action'];
    
    try {
        // Handler for teams list
        if ($action === 'get_teams') {
            $teams_sql = "SELECT team_id, team_name FROM teams ORDER BY team_name";
            $teams_result = $dbconn->query($teams_sql);
            $teams = [];
            
            while($team = $teams_result->fetch_assoc()) {
                $teams[] = $team;
            }
            
            echo json_encode($teams);
            exit;
        }
        
        // Handler for summary statistics
        else if ($action === 'get_stats') {
            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
            $selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
            
            // Build SQL conditions
            $team_condition = $selected_team > 0 ? "AND p.team_id = " . $selected_team : "";
            
            // Get overall summary statistics
            $sql_stats = "SELECT 
                IFNULL(SUM(sales), 0) as total_sales,
                IFNULL(SUM(profit), 0) as total_profit,
                IFNULL(SUM(ads_spend), 0) as total_ads_spend,
                IFNULL(SUM(item_cost), 0) as total_cogs,
                IFNULL(SUM(cod), 0) as total_shipping,
                COUNT(*) as total_products,
                COUNT(DISTINCT product_name) as unique_products,
                IFNULL(SUM(unit_sold), 0) as total_units,
                IFNULL(SUM(purchase), 0) as total_orders
            FROM products p
            WHERE created_at BETWEEN ? AND ? $team_condition";

            $stmt_stats = $dbconn->prepare($sql_stats);
            $stmt_stats->bind_param("ss", $start_date, $end_date);
            $stmt_stats->execute();
            $stats = $stmt_stats->get_result()->fetch_assoc();
            
            echo json_encode($stats);
            exit;
        }
        
        // Handler for chart data
        else if ($action === 'get_charts') {
            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
            $selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
            
            // Build SQL conditions
            $team_condition = $selected_team > 0 ? "AND p.team_id = " . $selected_team : "";
            
            // Get daily sales data for chart (optimized to limit days)
            $sql_daily_sales = "SELECT 
                DATE(created_at) as sale_date,
                SUM(sales) as daily_sales,
                SUM(profit) as daily_profit,
                SUM(ads_spend) as daily_ads_spend,
                SUM(item_cost) as daily_cogs
            FROM products p
            WHERE created_at BETWEEN ? AND ? $team_condition
            GROUP BY DATE(created_at)
            ORDER BY sale_date";

            $stmt_daily_sales = $dbconn->prepare($sql_daily_sales);
            $stmt_daily_sales->bind_param("ss", $start_date, $end_date);
            $stmt_daily_sales->execute();
            $result_daily_sales = $stmt_daily_sales->get_result();
            
            $daily_sales = [];
            while ($row = $result_daily_sales->fetch_assoc()) {
                $daily_sales[] = $row;
            }
            
            echo json_encode($daily_sales);
            exit;
        }
        
        // Handler for team sales data with pagination
        else if ($action === 'get_team_sales') {
            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 10;
            
            // Calculate offset
            $offset = ($page - 1) * $page_size;
            
            // Get total count for pagination
            $count_sql = "SELECT COUNT(DISTINCT t.team_id) as total FROM teams t";
            $count_result = $dbconn->query($count_sql);
            $total_count = $count_result->fetch_assoc()['total'];
            $total_pages = ceil($total_count / $page_size);
            
            // Get sales by team with pagination
            $sql_team_sales = "SELECT 
                t.team_name,
                t.team_id,
                IFNULL(SUM(p.sales), 0) as team_sales,
                IFNULL(SUM(p.profit), 0) as team_profit,
                IFNULL(SUM(p.ads_spend), 0) as team_ads_spend,
                IFNULL(SUM(p.item_cost), 0) as team_cogs,
                IFNULL(SUM(p.cod), 0) as team_shipping,
                COUNT(p.id) as product_count,
                IFNULL(SUM(p.unit_sold), 0) as units_sold,
                IFNULL(SUM(p.purchase), 0) as orders_count
            FROM teams t
            LEFT JOIN products p ON t.team_id = p.team_id AND p.created_at BETWEEN ? AND ?
            GROUP BY t.team_id
            ORDER BY team_sales DESC
            LIMIT ? OFFSET ?";

            $stmt_team_sales = $dbconn->prepare($sql_team_sales);
            $stmt_team_sales->bind_param("ssii", $start_date, $end_date, $page_size, $offset);
            $stmt_team_sales->execute();
            $result_team_sales = $stmt_team_sales->get_result();
            
            $team_sales = [];
            while ($row = $result_team_sales->fetch_assoc()) {
                // Calculate profit margin and ROI
                $row['profit_margin'] = $row['team_sales'] > 0 ? ($row['team_profit'] / $row['team_sales']) * 100 : 0;
                $row['roi'] = $row['team_ads_spend'] > 0 ? ($row['team_profit'] / $row['team_ads_spend']) * 100 : 0;
                
                $team_sales[] = $row;
            }
            
            $response = [
                'data' => $team_sales,
                'page' => $page,
                'page_size' => $page_size,
                'total_pages' => $total_pages,
                'total_items' => $total_count
            ];
            
            echo json_encode($response);
            exit;
        }
        
        // Handler for winning DNA with pagination
        else if ($action === 'get_winning_dna') {
            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
            $selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 10;
            
            // Build SQL conditions
            $team_condition = $selected_team > 0 ? "AND team_id = " . $selected_team : "";
            
            // Calculate offset
            $offset = ($page - 1) * $page_size;
            
            // Get count for pagination (for products with profit > 0 and ads_spend > 0)
            $count_sql = "SELECT COUNT(DISTINCT product_name) as total 
                FROM products 
                WHERE created_at BETWEEN ? AND ? 
                $team_condition
                GROUP BY product_name
                HAVING SUM(profit) > 0 AND SUM(ads_spend) > 0";
            
            $stmt_count = $dbconn->prepare($count_sql);
            $stmt_count->bind_param("ss", $start_date, $end_date);
            $stmt_count->execute();
            $count_result = $stmt_count->get_result();
            
            $total_count = 0;
            while($row = $count_result->fetch_assoc()) {
                $total_count++;
            }
            
            $total_pages = ceil($total_count / $page_size);
            
            // Get Winning DNA (top performing products) with pagination
            $sql_winning_dna = "SELECT 
                product_name,
                SUM(unit_sold) as total_sold,
                SUM(sales) as total_sales,
                SUM(profit) as total_profit,
                SUM(profit)/SUM(sales)*100 as profit_margin,
                SUM(ads_spend) as total_ads_spend,
                (SUM(profit)/SUM(ads_spend))*100 as roi,
                team_id
            FROM products
            WHERE created_at BETWEEN ? AND ? $team_condition
            GROUP BY product_name
            HAVING SUM(profit) > 0 AND SUM(ads_spend) > 0
            ORDER BY roi DESC
            LIMIT ? OFFSET ?";

            $stmt_winning_dna = $dbconn->prepare($sql_winning_dna);
            $stmt_winning_dna->bind_param("ssii", $start_date, $end_date, $page_size, $offset);
            $stmt_winning_dna->execute();
            $result_winning_dna = $stmt_winning_dna->get_result();
            
            $winning_dna = [];
            while ($row = $result_winning_dna->fetch_assoc()) {
                $winning_dna[] = $row;
            }
            
            $response = [
                'data' => $winning_dna,
                'page' => $page,
                'page_size' => $page_size,
                'total_pages' => $total_pages,
                'total_items' => $total_count
            ];
            
            echo json_encode($response);
            exit;
        }
        
        // Handler for top products with pagination
        else if ($action === 'get_top_products') {
            $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
            $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-7 days'));
            $selected_team = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;
            $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
            $page_size = isset($_GET['page_size']) ? intval($_GET['page_size']) : 10;
            
            // Build SQL conditions
            $team_condition = $selected_team > 0 ? "AND team_id = " . $selected_team : "";
            
            // Calculate offset
            $offset = ($page - 1) * $page_size;
            
            // Get count for pagination
            $count_sql = "SELECT COUNT(DISTINCT product_name) as total 
                FROM products 
                WHERE created_at BETWEEN ? AND ? 
                $team_condition";
            
            $stmt_count = $dbconn->prepare($count_sql);
            $stmt_count->bind_param("ss", $start_date, $end_date);
            $stmt_count->execute();
            $count_result = $stmt_count->get_result()->fetch_assoc();
            $total_count = $count_result['total'];
            $total_pages = ceil($total_count / $page_size);
            
            // Get top selling products with pagination
            $sql_top_products = "SELECT 
                product_name,
                SUM(unit_sold) as total_sold,
                SUM(sales) as total_sales,
                SUM(profit) as total_profit,
                AVG(profit/sales)*100 as profit_margin,
                SUM(ads_spend) as ads_spend,
                SUM(item_cost) as cogs,
                team_id
            FROM products
            WHERE created_at BETWEEN ? AND ? $team_condition
            GROUP BY product_name
            ORDER BY total_profit DESC
            LIMIT ? OFFSET ?";

            $stmt_top_products = $dbconn->prepare($sql_top_products);
            $stmt_top_products->bind_param("ssii", $start_date, $end_date, $page_size, $offset);
            $stmt_top_products->execute();
            $result_top_products = $stmt_top_products->get_result();
            
            $top_products = [];
            while ($row = $result_top_products->fetch_assoc()) {
                $top_products[] = $row;
            }
            
            $response = [
                'data' => $top_products,
                'page' => $page,
                'page_size' => $page_size,
                'total_pages' => $total_pages,
                'total_items' => $total_count
            ];
            
            echo json_encode($response);
            exit;
        }
        
        // Handler for team comparison
        else if ($action === 'get_team_comparison') {
            $period = isset($_GET['period']) ? $_GET['period'] : '7';
            $end_date = date('Y-m-d');
            $start_date = date('Y-m-d', strtotime("-$period days"));
            
            // Get team metrics
            $sql_team_metrics = "SELECT 
                t.team_name,
                t.team_id,
                IFNULL(SUM(p.sales), 0) as team_sales,
                IFNULL(SUM(p.profit), 0) as team_profit,
                IFNULL(SUM(p.ads_spend), 0) as team_ads_spend,
                IFNULL(SUM(p.item_cost), 0) as team_cogs,
                IFNULL(SUM(p.cod), 0) as team_shipping,
                COUNT(DISTINCT p.product_name) as product_count,
                IFNULL(SUM(p.unit_sold), 0) as units_sold,
                IFNULL(SUM(p.purchase), 0) as orders_count
            FROM teams t
            LEFT JOIN products p ON t.team_id = p.team_id AND p.created_at BETWEEN ? AND ?
            GROUP BY t.team_id
            ORDER BY team_sales DESC";

            $stmt_team_metrics = $dbconn->prepare($sql_team_metrics);
            $stmt_team_metrics->bind_param("ss", $start_date, $end_date);
            $stmt_team_metrics->execute();
            $result_team_metrics = $stmt_team_metrics->get_result();
            
            $team_metrics = [];
            // Get previous period for growth calculation
            $prev_end_date = date('Y-m-d', strtotime("-$period days"));
            $prev_start_date = date('Y-m-d', strtotime("-" . ($period * 2) . " days"));

            while ($team = $result_team_metrics->fetch_assoc()) {
                // Calculate profit margin and ROI
                $team['profit_margin'] = $team['team_sales'] > 0 ? ($team['team_profit'] / $team['team_sales']) * 100 : 0;
                $team['roi'] = $team['team_ads_spend'] > 0 ? ($team['team_profit'] / $team['team_ads_spend']) * 100 : 0;
                
                // Get previous period data for this team (using prepared statement to prevent SQL injection)
                $sql_prev = "SELECT 
                    IFNULL(SUM(sales), 0) as prev_sales,
                    IFNULL(SUM(profit), 0) as prev_profit
                FROM products 
                WHERE team_id = ? AND created_at BETWEEN ? AND ?";
                
                $stmt_prev = $dbconn->prepare($sql_prev);
                $stmt_prev->bind_param("iss", $team['team_id'], $prev_start_date, $prev_end_date);
                $stmt_prev->execute();
                $prev_data = $stmt_prev->get_result()->fetch_assoc();
                
                // Calculate growth
                if ($prev_data && $prev_data['prev_sales'] > 0) {
                    $team['growth'] = (($team['team_sales'] - $prev_data['prev_sales']) / $prev_data['prev_sales']) * 100;
                } else {
                    $team['growth'] = 0; // No previous sales or no data
                }
                
                $team_metrics[] = $team;
            }

            // Prepare response
            $response = [
                'period' => $period,
                'team_metrics' => $team_metrics
            ];

            echo json_encode($response);
            exit;
        }
        
        // If action not recognized
        echo json_encode(['error' => 'Invalid action specified']);
        exit;
        
    } catch (Exception $e) {
        // Log error and return error response
        logError('API Error', $e);
        echo json_encode(['error' => 'An error occurred: ' . $e->getMessage()]);
        exit;
    }
}
?>