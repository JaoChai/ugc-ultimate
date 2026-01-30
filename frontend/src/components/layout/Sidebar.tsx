import { NavLink } from 'react-router-dom';
import {
  LayoutDashboard,
  FolderKanban,
  Tv2,
  Settings,
  Key,
  ChevronLeft,
  ChevronRight,
} from 'lucide-react';
import { useState } from 'react';

const navigation = [
  { name: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
  { name: 'Projects', href: '/projects', icon: FolderKanban },
  { name: 'Channels', href: '/channels', icon: Tv2 },
  { name: 'API Keys', href: '/settings/api-keys', icon: Key },
  { name: 'Settings', href: '/settings', icon: Settings },
];

export default function Sidebar() {
  const [collapsed, setCollapsed] = useState(false);

  return (
    <aside
      className={`${
        collapsed ? 'w-16' : 'w-64'
      } bg-card border-r border-border flex flex-col transition-all duration-300`}
    >
      {/* Logo */}
      <div className="h-16 flex items-center justify-between px-4 border-b border-border">
        {!collapsed && (
          <div className="flex items-center gap-2">
            <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
              <span className="text-primary-foreground font-bold text-sm">U</span>
            </div>
            <span className="font-semibold text-foreground">UGC Ultimate</span>
          </div>
        )}
        <button
          onClick={() => setCollapsed(!collapsed)}
          className="p-1.5 rounded-md hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors"
        >
          {collapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
        </button>
      </div>

      {/* Navigation */}
      <nav className="flex-1 py-4 px-2 space-y-1">
        {navigation.map((item) => (
          <NavLink
            key={item.name}
            to={item.href}
            className={({ isActive }) =>
              `flex items-center gap-3 px-3 py-2.5 rounded-lg transition-colors ${
                isActive
                  ? 'bg-primary text-primary-foreground'
                  : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
              }`
            }
          >
            <item.icon size={20} />
            {!collapsed && <span className="font-medium">{item.name}</span>}
          </NavLink>
        ))}
      </nav>

      {/* Footer */}
      {!collapsed && (
        <div className="p-4 border-t border-border">
          <div className="bg-secondary/50 rounded-lg p-3">
            <p className="text-xs text-muted-foreground">Credits Remaining</p>
            <p className="text-lg font-semibold text-foreground">-</p>
          </div>
        </div>
      )}
    </aside>
  );
}
