// Type declarations for lucide-react when node_modules is in Docker volume
declare module 'lucide-react' {
  import { FC, SVGProps } from 'react'

  export interface IconProps extends SVGProps<SVGSVGElement> {
    size?: number | string
    strokeWidth?: number | string
    absoluteStrokeWidth?: boolean
  }

  export type Icon = FC<IconProps>

  export const Filter: Icon
  export const Menu: Icon
  export const X: Icon
  export const Home: Icon
  export const BarChart3: Icon
  export const Info: Icon
  export const MessageSquare: Icon
  export const Plug: Icon
  export const Wrench: Icon
  export const ExternalLink: Icon
  export const Heart: Icon
  export const RefreshCw: Icon
}

