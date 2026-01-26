import { useState, useEffect } from 'react'
import {
  ChartContainer,
  ChartTooltip,
  ChartTooltipContent,
  type ChartConfig,
} from '@/components/ui/chart'
import {
  AreaChart,
  Area,
  XAxis,
  YAxis,
  PieChart,
  Pie,
  Cell,
  ResponsiveContainer,
} from 'recharts'

interface ApiResponse {
  cols: { label: string; type: string }[]
  rows: { c: { v: unknown }[] }[]
}

interface SubmissionData {
  date: string
  eac: number
  cueripper: number
  cuetools: number
}

interface PieData {
  name: string
  value: number
}

interface TotalsData {
  unique_tocs: number
  submissions: number
}

const submissionsConfig: ChartConfig = {
  eac: { label: 'EAC', color: '#3b82f6' },
  cueripper: { label: 'CUERipper', color: '#ef4444' },
  cuetools: { label: 'CUETools', color: '#22c55e' },
}

const PIE_COLORS = [
  '#3b82f6', '#ef4444', '#22c55e', '#f59e0b', '#8b5cf6',
  '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1',
  '#14b8a6', '#a855f7', '#eab308', '#0ea5e9', '#d946ef',
]

function transformSubmissions(data: ApiResponse): SubmissionData[] {
  return data.rows.map(row => ({
    date: String(row.c[0]?.v || ''),
    eac: Number(row.c[1]?.v || 0),
    cueripper: Number(row.c[2]?.v || 0),
    cuetools: Number(row.c[3]?.v || 0),
  }))
}

function transformPieData(data: ApiResponse): PieData[] {
  return data.rows
    .map(row => ({
      name: String(row.c[0]?.v || ''),
      value: Number(row.c[1]?.v || 0),
    }))
    .slice(0, 15) // Limit to top 15 for readability
}

interface ChartSectionProps {
  title: string
  children: React.ReactNode
  loading?: boolean
}

function ChartSection({ title, children, loading }: ChartSectionProps) {
  return (
    <div className="chart-section">
      <h2 className="chart-title">{title}</h2>
      {loading ? (
        <div className="chart-loading">Loading...</div>
      ) : (
        children
      )}
    </div>
  )
}

interface PieChartCardProps {
  title: string
  data: PieData[]
  loading: boolean
}

function PieChartCard({ title, data, loading }: PieChartCardProps) {
  return (
    <div className="pie-chart-card">
      <h3 className="pie-chart-title">{title}</h3>
      {loading ? (
        <div className="chart-loading">Loading...</div>
      ) : (
        <div className="pie-chart-container">
          <ResponsiveContainer width="100%" height={250}>
            <PieChart>
              <Pie
                data={data}
                dataKey="value"
                nameKey="name"
                cx="50%"
                cy="50%"
                outerRadius={80}
                label={({ name, percent }: { name: string; percent: number }) => 
                  percent > 0.03 ? `${name.substring(0, 12)}${name.length > 12 ? '…' : ''}` : ''
                }
                labelLine={false}
              >
                {data.map((_, index) => (
                  <Cell key={index} fill={PIE_COLORS[index % PIE_COLORS.length]} />
                ))}
              </Pie>
              <ChartTooltip
                content={({ active, payload }: { active?: boolean; payload?: Array<{ name?: string; value?: number }> }) => {
                  if (!active || !payload?.length) return null
                  const item = payload[0]
                  return (
                    <div className="chart-tooltip">
                      <div className="tooltip-label">{item.name}</div>
                      <div className="tooltip-value">{item.value?.toLocaleString()}</div>
                    </div>
                  )
                }}
              />
            </PieChart>
          </ResponsiveContainer>
        </div>
      )}
    </div>
  )
}

export function Stats() {
  const [totals, setTotals] = useState<TotalsData | null>(null)
  const [dailyData, setDailyData] = useState<SubmissionData[]>([])
  const [hourlyData, setHourlyData] = useState<SubmissionData[]>([])
  const [drivesData, setDrivesData] = useState<PieData[]>([])
  const [agentsData, setAgentsData] = useState<PieData[]>([])
  const [pregapsData, setPregapsData] = useState<PieData[]>([])
  const [loading, setLoading] = useState({
    daily: true,
    hourly: true,
    drives: true,
    agents: true,
    pregaps: true,
  })

  // Fetch totals every 5 seconds
  useEffect(() => {
    const fetchTotals = () => {
      fetch('/api/stats?type=totals')
        .then(res => res.json())
        .then((data: TotalsData) => setTotals(data))
        .catch(() => {})
    }

    fetchTotals() // Initial fetch
    const interval = setInterval(fetchTotals, 5000)

    return () => clearInterval(interval)
  }, [])

  useEffect(() => {
    // Fetch daily submissions
    fetch('/api/stats?type=submissions&count=365')
      .then(res => res.json())
      .then((data: SubmissionData[]) => {
        setDailyData(data)
        setLoading(prev => ({ ...prev, daily: false }))
      })
      .catch(() => setLoading(prev => ({ ...prev, daily: false })))

    // Fetch hourly submissions
    fetch('/api/stats?type=submissions&count=336&hourly=1')
      .then(res => res.json())
      .then((data: SubmissionData[]) => {
        setHourlyData(data)
        setLoading(prev => ({ ...prev, hourly: false }))
      })
      .catch(() => setLoading(prev => ({ ...prev, hourly: false })))

    // Fetch drives
    fetch('/api/stats?type=drives')
      .then(res => res.json())
      .then((data: Array<{ drive: string; count: number }>) => {
        setDrivesData(data.map(d => ({ name: d.drive, value: d.count })).slice(0, 15))
        setLoading(prev => ({ ...prev, drives: false }))
      })
      .catch(() => setLoading(prev => ({ ...prev, drives: false })))

    // Fetch agents
    fetch('/api/stats?type=agents')
      .then(res => res.json())
      .then((data: Array<{ agent: string; count: number }>) => {
        setAgentsData(data.map(a => ({ name: a.agent, value: a.count })).slice(0, 15))
        setLoading(prev => ({ ...prev, agents: false }))
      })
      .catch(() => setLoading(prev => ({ ...prev, agents: false })))

    // Fetch pregaps
    fetch('/api/stats?type=pregaps')
      .then(res => res.json())
      .then((data: Array<{ pregap: string; count: number }>) => {
        setPregapsData(data.map(p => ({ name: p.pregap, value: p.count })).slice(0, 15))
        setLoading(prev => ({ ...prev, pregaps: false }))
      })
      .catch(() => setLoading(prev => ({ ...prev, pregaps: false })))
  }, [])

  return (
    <div className="stats-page">
      {/* Totals counter */}
      {totals && (
        <div className="stats-totals">
          <div className="totals-item">
            <span className="totals-value">{totals.unique_tocs.toLocaleString()}</span>
            <span className="totals-label">discs</span>
          </div>
          <div className="totals-item">
            <span className="totals-value">{totals.submissions.toLocaleString()}</span>
            <span className="totals-label">rips</span>
          </div>
        </div>
      )}

      <ChartSection title="Daily Submissions" loading={loading.daily}>
        <ChartContainer config={submissionsConfig} className="area-chart">
          <AreaChart data={dailyData}>
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              tickFormatter={(value: string | number) => String(value).slice(5)} // Show MM-DD
              interval="preserveStartEnd"
            />
            <YAxis
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              tickFormatter={(value: string | number) => Number(value).toLocaleString()}
            />
            <ChartTooltip content={<ChartTooltipContent />} />
            <Area
              type="monotone"
              dataKey="cuetools"
              stroke="var(--color-cuetools)"
              fill="var(--color-cuetools)"
              fillOpacity={0.6}
            />
            <Area
              type="monotone"
              dataKey="cueripper"
              stroke="var(--color-cueripper)"
              fill="var(--color-cueripper)"
              fillOpacity={0.6}
            />
            <Area
              type="monotone"
              dataKey="eac"
              stroke="var(--color-eac)"
              fill="var(--color-eac)"
              fillOpacity={0.6}
            />
          </AreaChart>
        </ChartContainer>
      </ChartSection>

      <ChartSection title="Hourly Submissions (Last 14 Days)" loading={loading.hourly}>
        <ChartContainer config={submissionsConfig} className="area-chart">
          <AreaChart data={hourlyData}>
            <XAxis
              dataKey="date"
              tickLine={false}
              axisLine={false}
              tickMargin={8}
              interval="preserveStartEnd"
            />
            <YAxis
              tickLine={false}
              axisLine={false}
              tickMargin={8}
            />
            <ChartTooltip content={<ChartTooltipContent />} />
            <Area
              type="monotone"
              dataKey="cuetools"
              stroke="var(--color-cuetools)"
              fill="var(--color-cuetools)"
              fillOpacity={0.6}
            />
            <Area
              type="monotone"
              dataKey="cueripper"
              stroke="var(--color-cueripper)"
              fill="var(--color-cueripper)"
              fillOpacity={0.6}
            />
            <Area
              type="monotone"
              dataKey="eac"
              stroke="var(--color-eac)"
              fill="var(--color-eac)"
              fillOpacity={0.6}
            />
          </AreaChart>
        </ChartContainer>
      </ChartSection>

      <div className="pie-charts-row">
        <PieChartCard title="Drives" data={drivesData} loading={loading.drives} />
        <PieChartCard title="Agents" data={agentsData} loading={loading.agents} />
        <PieChartCard title="Pregaps" data={pregapsData} loading={loading.pregaps} />
      </div>
    </div>
  )
}

