import { useState } from 'react'
import { Button } from './ui/button'
import { Popover, PopoverContent, PopoverTrigger } from './ui/popover'
import { User, LogOut } from 'lucide-react'

interface UserMenuProps {
  user: { email: string; role: string }
  onLogout: () => void
}

export function UserMenu({ user, onLogout }: UserMenuProps) {
  const [open, setOpen] = useState(false)

  const handleLogout = async () => {
    try {
      await fetch('/api/auth/logout', { method: 'POST' })
      onLogout()
    } catch (err) {
      console.error('Logout failed:', err)
    }
  }

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button
          variant="outline"
          size="sm"
          className="refresh-button gap-2"
          title={user.email}
        >
          <User className="size-4" />
          {user.email.split('@')[0]}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-64 user-menu-popup">
        <div className="space-y-2">
          <div className="text-sm text-muted-foreground">
            <div className="font-medium text-foreground">{user.email}</div>
            <div className="text-xs">Role: {user.role}</div>
          </div>
          <Button
            variant="ghost"
            size="sm"
            className="w-full justify-start gap-2"
            onClick={handleLogout}
          >
            <LogOut className="size-4" />
            Logout
          </Button>
        </div>
      </PopoverContent>
    </Popover>
  )
}
