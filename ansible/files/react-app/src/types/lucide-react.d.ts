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
  // Add more icons here as needed
}

