import { Button } from './ui/button'
import { LogIn } from 'lucide-react'

export function LoginButton() {
  const handleLogin = () => {
    window.location.href = '/api/auth/login'
  }

  return (
    <Button
      onClick={handleLogin}
      variant="outline"
      size="sm"
      className="refresh-button"
      title="Login with Google"
    >
      <LogIn className="size-4" />
    </Button>
  )
}
