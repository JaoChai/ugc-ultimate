import { DashboardLayout } from '@/components/layout';
import { useAuth } from '@/contexts/AuthContext';
import { Link } from 'react-router-dom';
import { User, Key, Bell, Palette, ChevronRight, Bot } from 'lucide-react';

const settingsLinks = [
  {
    name: 'API Keys',
    description: 'Manage your kie.ai and R2 API keys',
    href: '/settings/api-keys',
    icon: Key,
  },
  {
    name: 'AI Agents',
    description: 'Configure system prompts and models for pipeline agents',
    href: '/settings/agents',
    icon: Bot,
  },
  {
    name: 'Notifications',
    description: 'Configure email and push notifications',
    href: '/settings/notifications',
    icon: Bell,
    disabled: true,
  },
  {
    name: 'Appearance',
    description: 'Customize the look and feel',
    href: '/settings/appearance',
    icon: Palette,
    disabled: true,
  },
];

export default function Settings() {
  const { user } = useAuth();

  return (
    <DashboardLayout>
      {/* Header */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground">Settings</h1>
        <p className="text-muted-foreground mt-1">Manage your account and preferences</p>
      </div>

      {/* Profile section */}
      <div className="bg-card rounded-xl border border-border p-6 mb-6">
        <h2 className="text-lg font-semibold text-foreground mb-4">Profile</h2>
        <div className="flex items-center gap-4">
          <div className="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center">
            <User className="text-primary" size={32} />
          </div>
          <div>
            <p className="font-medium text-foreground">{user?.name}</p>
            <p className="text-sm text-muted-foreground">{user?.email}</p>
          </div>
        </div>
      </div>

      {/* Settings links */}
      <div className="bg-card rounded-xl border border-border divide-y divide-border">
        {settingsLinks.map((link) => (
          <Link
            key={link.name}
            to={link.disabled ? '#' : link.href}
            className={`flex items-center justify-between p-4 transition-colors ${
              link.disabled
                ? 'opacity-50 cursor-not-allowed'
                : 'hover:bg-secondary'
            }`}
            onClick={(e) => link.disabled && e.preventDefault()}
          >
            <div className="flex items-center gap-4">
              <div className="p-2 rounded-lg bg-secondary">
                <link.icon className="text-foreground" size={20} />
              </div>
              <div>
                <p className="font-medium text-foreground">
                  {link.name}
                  {link.disabled && (
                    <span className="ml-2 text-xs bg-secondary text-muted-foreground px-2 py-0.5 rounded">
                      Coming Soon
                    </span>
                  )}
                </p>
                <p className="text-sm text-muted-foreground">{link.description}</p>
              </div>
            </div>
            <ChevronRight className="text-muted-foreground" size={20} />
          </Link>
        ))}
      </div>

      {/* Danger zone */}
      <div className="bg-card rounded-xl border border-destructive/30 p-6 mt-6">
        <h2 className="text-lg font-semibold text-destructive mb-2">Danger Zone</h2>
        <p className="text-sm text-muted-foreground mb-4">
          Irreversible and destructive actions
        </p>
        <button
          disabled
          className="px-4 py-2 border border-destructive text-destructive rounded-lg hover:bg-destructive/10 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
        >
          Delete Account
        </button>
      </div>
    </DashboardLayout>
  );
}
