import { useAuth } from '@/contexts/AuthContext';
import { Bell, Search, LogOut, User, ChevronDown } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';

export default function Header() {
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
    <header className="h-16 bg-card border-b border-border px-6 flex items-center justify-between">
      {/* Search */}
      <div className="flex-1 max-w-md">
        <div className="relative">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={18} />
          <input
            type="text"
            placeholder="Search projects, channels..."
            className="w-full pl-10 pr-4 py-2 bg-secondary/50 border border-transparent rounded-lg text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:border-primary focus:bg-background transition-colors"
          />
        </div>
      </div>

      {/* Right side */}
      <div className="flex items-center gap-4">
        {/* Notifications */}
        <button className="relative p-2 rounded-lg hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors">
          <Bell size={20} />
          <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-primary rounded-full" />
        </button>

        {/* User dropdown */}
        <div className="relative" ref={dropdownRef}>
          <button
            onClick={() => setShowDropdown(!showDropdown)}
            className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-secondary transition-colors"
          >
            <div className="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
              <User size={18} className="text-primary" />
            </div>
            <span className="text-sm font-medium text-foreground hidden sm:block">
              {user?.name}
            </span>
            <ChevronDown size={16} className="text-muted-foreground" />
          </button>

          {showDropdown && (
            <div className="absolute right-0 top-full mt-2 w-48 bg-card rounded-lg border border-border shadow-lg py-1 z-50">
              <div className="px-4 py-2 border-b border-border">
                <p className="text-sm font-medium text-foreground">{user?.name}</p>
                <p className="text-xs text-muted-foreground">{user?.email}</p>
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
