import React, { useState, useEffect } from 'react';
import { LineChart, Line, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';

// Main Dashboard Component
const Dashboard = () => {
  // State for navigation and filters
  const [activeTab, setActiveTab] = useState('dashboard');
  const [startDate, setStartDate] = useState(getDefaultStartDate());
  const [endDate, setEndDate] = useState(getDefaultEndDate());
  const [selectedTeam, setSelectedTeam] = useState(0);
  const [comparisonPeriod, setComparisonPeriod] = useState('30');
  
  // State for storing fetched data
  const [dashboardData, setDashboardData] = useState(null);
  const [teamComparisonData, setTeamComparisonData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Helper functions for default dates
  function getDefaultStartDate() {
    const date = new Date();
    date.setDate(date.getDate() - 30);
    return date.toISOString().split('T')[0];
  }

  function getDefaultEndDate() {
    return new Date().toISOString().split('T')[0];
  }

  // Fetch dashboard data
  useEffect(() => {
    if (activeTab === 'dashboard') {
      fetchDashboardData();
    }
  }, [activeTab, startDate, endDate, selectedTeam]);

  // Fetch team comparison data
  useEffect(() => {
    if (activeTab === 'team_comparison') {
      fetchTeamComparisonData();
    }
  }, [activeTab, comparisonPeriod]);

  // Function to fetch dashboard data
  const fetchDashboardData = async () => {
    setLoading(true);
    try {
      const response = await fetch(`get_dashboard_data.php?start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`);
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      const data = await response.json();
      setDashboardData(data);
      setError(null);
    } catch (err) {
      console.error("Error fetching dashboard data:", err);
      setError("Failed to load dashboard data. Please check your connection and try again.");
    } finally {
      setLoading(false);
    }
  };

  // Function to fetch team comparison data
  const fetchTeamComparisonData = async () => {
    setLoading(true);
    try {
      const response = await fetch(`get_team_comparison.php?period=${comparisonPeriod}`);
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      const data = await response.json();
      setTeamComparisonData(data);
      setError(null);
    } catch (err) {
      console.error("Error fetching team comparison data:", err);
      setError("Failed to load team comparison data. Please check your connection and try again.");
    } finally {
      setLoading(false);
    }
  };

  // Apply dashboard filters
  const handleDashboardFilter = (e) => {
    e.preventDefault();
    fetchDashboardData();
  };

  // Apply team comparison filters
  const handleComparisonFilter = () => {
    fetchTeamComparisonData();
  };

  // Format currency
  const formatCurrency = (value) => {
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
          <button className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
            <i className="fa-solid fa-medal mr-3 w-5 text-center"></i>
            <span>Winning DNA</span>
          </button>
        </li>
        <li className="mb-1">
          <button className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
            <i className="fas fa-users mr-3 w-5 text-center"></i>
            <span>Teams</span>
          </button>
        </li>
        <li className="mb-1">
          <button className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
            <i className="fas fa-boxes mr-3 w-5 text-center"></i>
            <span>All Products</span>
          </button>
        </li>
        <li className="mb-1">
          <button className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
            <i className="fas fa-calculator mr-3 w-5 text-center"></i>
            <span>Commission Calculator</span>
          </button>
        </li>
        <li className="mb-1">
          <button className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
            <i className="fas fa-file-download mr-3 w-5 text-center"></i>
            <span>Reports</span>
          </button>
        </li>
        <li>
          <button className="flex items-center w-full px-5 py-3 text-left hover:bg-white/10 transition-all">
            <i className="fas fa-sign-out-alt mr-3 w-5 text-center"></i>
            <span>Logout</span>
          </button>
        </li>
      </ul>
    </div>
  );

  // Loading indicator component
  const LoadingIndicator = () => (
    <div className="flex justify-center items-center h-64">
      <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-800"></div>
    </div>
  );

  // Error message component
  const ErrorMessage = ({ message }) => (
    <div className="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6">
      <strong className="font-bold">Error: </strong>
      <span className="block sm:inline">{message}</span>
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

  // Dashboard Content
  const DashboardContent = () => {
    if (loading) return <LoadingIndicator />;
    if (error) return <ErrorMessage message={error} />;
    if (!dashboardData) return <div>No data available</div>;

    const { stats, daily_sales, team_sales, winning_dna, top_products, teams } = dashboardData;

    return (
      <div>
        {/* Page Header & Date Filter */}
        <div className="flex justify-between items-center mb-5 pb-4 border-b border-gray-200">
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
            value={stats.total_orders.toLocaleString()} 
            change={`Units: ${stats.total_units.toLocaleString()}`}
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
            value={stats.unique_products.toLocaleString()} 
            change={`${Math.round((new Date(endDate) - new Date(startDate)) / (1000 * 60 * 60 * 24))} days`}
          />
        </div>
        
        {/* Charts */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
          <div className="bg-white p-6 rounded-lg shadow-md">
            <h3 className="text-lg font-semibold mb-4">Sales & Profit Trend</h3>
            <div className="h-80">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={daily_sales}>
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
                <LineChart data={daily_sales}>
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
        </div>
        
        {/* Team Performance Table */}
        <div className="bg-white rounded-lg shadow-md mb-8 overflow-hidden">
          <div className="flex justify-between items-center bg-gray-50 p-4 border-b border-gray-200">
            <h3 className="text-lg font-semibold text-blue-900">Team Performance</h3>
            <button 
              onClick={() => window.location.href = `team_performance_export.php?start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`}
              className="bg-blue-800 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700"
            >
              <i className="fas fa-download mr-1"></i> Export
            </button>
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
                  <th className="text-left p-3">Shipping (RM)</th>
                  <th className="text-left p-3">Orders</th>
                  <th className="text-left p-3">Units Sold</th>
                </tr>
              </thead>
              <tbody>
                {team_sales.map((team) => (
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
        </div>
        
        {/* Winning DNA Table */}
        <div className="bg-white rounded-lg shadow-md mb-8 overflow-hidden">
          <div className="flex justify-between items-center bg-gray-50 p-4 border-b border-gray-200">
            <h3 className="text-lg font-semibold text-blue-900">Winning DNA (Top ROI Products)</h3>
            <button 
              onClick={() => window.location.href = `winning_dna_export.php?start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`}
              className="bg-blue-800 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700"
            >
              <i className="fas fa-download mr-1"></i> Export
            </button>
          </div>
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
                {winning_dna.map((product, index) => (
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
        </div>
        
        {/* Top Products Table */}
        <div className="bg-white rounded-lg shadow-md overflow-hidden">
          <div className="flex justify-between items-center bg-gray-50 p-4 border-b border-gray-200">
            <h3 className="text-lg font-semibold text-blue-900">Top Profitable Products</h3>
            <button 
              onClick={() => window.location.href = `top_products_export.php?start_date=${startDate}&end_date=${endDate}&team_id=${selectedTeam}`}
              className="bg-blue-800 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700"
            >
              <i className="fas fa-download mr-1"></i> Export
            </button>
          </div>
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
                {top_products.map((product, index) => (
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
        </div>
      </div>
    );
  };
  
  // Team Comparison Content
  const TeamComparisonContent = () => {
    if (loading) return <LoadingIndicator />;
    if (error) return <ErrorMessage message={error} />;
    if (!teamComparisonData) return <div>No data available</div>;
    
    const { team_metrics } = teamComparisonData;
    // Filter out teams with no sales
    const activeTeams = team_metrics.filter(team => parseFloat(team.team_sales) > 0);
    
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
                  // Wait for state to update then fetch
                  setTimeout(handleComparisonFilter, 0);
                }}
              >
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
          {team_metrics.map(team => (
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
            <button className="bg-blue-800 text-white px-3 py-1.5 rounded text-sm hover:bg-blue-700">
              <i className="fas fa-download mr-1"></i> Export
            </button>
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
                {team_metrics.map((team) => (
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
  
  return (
    <div className="flex min-h-screen bg-gray-100">
      <Sidebar />
      <div className="ml-64 w-full p-6">
        {activeTab === 'dashboard' ? <DashboardContent /> : <TeamComparisonContent />}
      </div>
    </div>
  );
};

export default Dashboard;