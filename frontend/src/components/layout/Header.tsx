import { useAuth } from '@/contexts/AuthContext';
import { Bell, Search, LogOut, User, ChevronDown, Menu } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

interface HeaderProps {
  onMobileMenuToggle?: () => void;
}

export default function Header({ onMobileMenuToggle }: HeaderProps) {
  const { user, logout } = useAuth();
  const [showDropdown, setShowDropdown] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setShowDropdown(false);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  return (
    <header className="h-16 bg-white border-b border-slate-200 px-4 sm:px-6 flex items-center justify-between gap-4">
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
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={18} />
          <Input
            type="text"
            placeholder="Search projects, channels..."
            className="pl-10 bg-slate-50 border-slate-200 focus:border-slate-300 focus:ring-slate-200"
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
        {/* Notifications */}
        <Button variant="ghost" size="icon" className="relative">
          <Bell size={20} className="text-slate-500" />
          <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-slate-900 rounded-full" />
        </Button>

        {/* User dropdown */}
        <div className="relative" ref={dropdownRef}>
          <button
            onClick={() => setShowDropdown(!showDropdown)}
            className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
          >
            <div className="w-8 h-8 bg-slate-100 rounded-full flex items-center justify-center">
              <User size={18} className="text-slate-500" />
            </div>
            <span className="text-sm font-medium text-slate-900 hidden sm:block max-w-[100px] truncate">
              {user?.name}
            </span>
            <ChevronDown size={16} className="text-slate-400 hidden sm:block" />
          </button>

          {showDropdown && (
            <div className="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg border border-slate-200 shadow-lg py-1 z-50">
              <div className="px-4 py-2 border-b border-slate-200">
                <p className="text-sm font-medium text-slate-900 truncate">{user?.name}</p>
                <p className="text-xs text-slate-500 truncate">{user?.email}</p>
              </div>
              <button
                onClick={logout}
                className="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-slate-50 flex items-center gap-2 transition-colors cursor-pointer"
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
