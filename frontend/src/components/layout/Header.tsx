import { useAuth } from '@/contexts/AuthContext';
import { useTheme } from '@/contexts/ThemeContext';
import { Bell, Search, LogOut, User, ChevronDown, Menu, Sun, Moon, Monitor } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface HeaderProps {
  onMobileMenuToggle?: () => void;
}

export default function Header({ onMobileMenuToggle }: HeaderProps) {
  const { user, logout } = useAuth();
  const { theme, setTheme, resolvedTheme } = useTheme();
  const [showDropdown, setShowDropdown] = useState(false);
  const [showThemeDropdown, setShowThemeDropdown] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const themeDropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setShowDropdown(false);
      }
      if (themeDropdownRef.current && !themeDropdownRef.current.contains(event.target as Node)) {
        setShowThemeDropdown(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <header className="h-16 bg-card border-b border-border px-4 sm:px-6 flex items-center justify-between gap-4">
      {/* Mobile menu button */}
      <Button
        variant="ghost"
        size="icon"
        className="lg:hidden flex-shrink-0"
        onClick={onMobileMenuToggle}
      >
        <Menu size={20} />
      </Button>

      {/* Search */}
      <div className="flex-1 max-w-md hidden sm:block">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={18} />
          <Input
            type="text"
            placeholder="Search projects, channels..."
            className="pl-10 bg-secondary/50 border-transparent focus:border-primary"
          />
        </div>
      </div>

      {/* Mobile search icon */}
      <Button variant="ghost" size="icon" className="sm:hidden flex-shrink-0">
        <Search size={20} />
      </Button>

      {/* Spacer for mobile */}
      <div className="flex-1 sm:hidden" />

      {/* Right side */}
      <div className="flex items-center gap-2 sm:gap-4 flex-shrink-0">
        {/* Theme toggle */}
        <div className="relative" ref={themeDropdownRef}>
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setShowThemeDropdown(!showThemeDropdown)}
          >
            {resolvedTheme === 'dark' ? <Moon size={20} /> : <Sun size={20} />}
          </Button>

          {showThemeDropdown && (
            <div className="absolute right-0 top-full mt-2 w-36 bg-card rounded-lg border border-border shadow-lg py-1 z-50">
              <button
                onClick={() => {
                  setTheme('light');
                  setShowThemeDropdown(false);
                }}
                className={`w-full px-3 py-2 text-left text-sm flex items-center gap-2 hover:bg-secondary transition-colors ${
                  theme === 'light' ? 'text-primary' : 'text-foreground'
                }`}
              >
                <Sun size={16} />
                Light
              </button>
              <button
                onClick={() => {
                  setTheme('dark');
                  setShowThemeDropdown(false);
                }}
                className={`w-full px-3 py-2 text-left text-sm flex items-center gap-2 hover:bg-secondary transition-colors ${
                  theme === 'dark' ? 'text-primary' : 'text-foreground'
                }`}
              >
                <Moon size={16} />
                Dark
              </button>
              <button
                onClick={() => {
                  setTheme('system');
                  setShowThemeDropdown(false);
                }}
                className={`w-full px-3 py-2 text-left text-sm flex items-center gap-2 hover:bg-secondary transition-colors ${
                  theme === 'system' ? 'text-primary' : 'text-foreground'
                }`}
              >
                <Monitor size={16} />
                System
              </button>
            </div>
          )}
        </div>

        {/* Notifications */}
        <Button variant="ghost" size="icon" className="relative">
          <Bell size={20} />
          <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-primary rounded-full" />
        </Button>

        {/* User dropdown */}
        <div className="relative" ref={dropdownRef}>
          <button
            onClick={() => setShowDropdown(!showDropdown)}
            className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-secondary transition-colors"
          >
            <div className="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
              <User size={18} className="text-primary" />
            </div>
            <span className="text-sm font-medium text-foreground hidden sm:block max-w-[100px] truncate">
              {user?.name}
            </span>
            <ChevronDown size={16} className="text-muted-foreground hidden sm:block" />
          </button>

          {showDropdown && (
            <div className="absolute right-0 top-full mt-2 w-48 bg-card rounded-lg border border-border shadow-lg py-1 z-50">
              <div className="px-4 py-2 border-b border-border">
                <p className="text-sm font-medium text-foreground truncate">{user?.name}</p>
                <p className="text-xs text-muted-foreground truncate">{user?.email}</p>
              </div>
              <button
                onClick={logout}
                className="w-full px-4 py-2 text-left text-sm text-destructive hover:bg-secondary flex items-center gap-2 transition-colors"
              >
                <LogOut size={16} />
                Logout
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
