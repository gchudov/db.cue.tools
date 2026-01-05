// Type declarations for recharts when node_modules is in Docker volume
declare module 'recharts' {
  import { FC, ReactNode, CSSProperties } from 'react'

  export interface ResponsiveContainerProps {
    width?: string | number
    height?: string | number
    children?: ReactNode
  }
  export const ResponsiveContainer: FC<ResponsiveContainerProps>

  export interface AreaChartProps {
    data?: unknown[]
    children?: ReactNode
  }
  export const AreaChart: FC<AreaChartProps>

  export interface AreaProps {
    type?: string
    dataKey?: string
    stackId?: string
    stroke?: string
    fill?: string
    fillOpacity?: number
  }
  export const Area: FC<AreaProps>

  export interface XAxisProps {
    dataKey?: string
    tickLine?: boolean
    axisLine?: boolean
    tickMargin?: number
    tickFormatter?: (value: string | number) => string
    interval?: string | number
  }
  export const XAxis: FC<XAxisProps>

  export interface YAxisProps {
    tickLine?: boolean
    axisLine?: boolean
    tickMargin?: number
    tickFormatter?: (value: string | number) => string
  }
  export const YAxis: FC<YAxisProps>

  export interface PieChartProps {
    children?: ReactNode
  }
  export const PieChart: FC<PieChartProps>

  export interface PieProps {
    data?: unknown[]
    dataKey?: string
    nameKey?: string
    cx?: string | number
    cy?: string | number
    outerRadius?: number
    innerRadius?: number
    label?: boolean | ((props: { name: string; percent: number }) => string)
    labelLine?: boolean
    children?: ReactNode
  }
  export const Pie: FC<PieProps>

  export interface CellProps {
    fill?: string
    style?: CSSProperties
  }
  export const Cell: FC<CellProps>

  export interface TooltipProps {
    content?: ReactNode | FC<{ active?: boolean; payload?: Array<{ name?: string; value?: number; dataKey?: string; color?: string }> }>
    [key: string]: unknown
  }
  export const Tooltip: FC<TooltipProps>
  export const Legend: FC<unknown>
  export type LegendProps = Record<string, unknown>
}

