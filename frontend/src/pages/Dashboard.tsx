import { DashboardLayout } from '@/components/layout';
import { useAuth } from '@/contexts/AuthContext';
import { Link } from 'react-router-dom';
import {
  FolderKanban,
  Tv2,
  Zap,
  Plus,
  ArrowRight,
  Clock,
  CheckCircle2,
  AlertCircle,
  Play,
} from 'lucide-react';

const stats = [
  {
    name: 'Total Projects',
    value: '0',
    icon: FolderKanban,
    change: '+0 this week',
    color: 'text-blue-500',
    bgColor: 'bg-blue-500/10',
  },
  {
    name: 'Active Channels',
    value: '0',
    icon: Tv2,
    change: '0 scheduled',
    color: 'text-green-500',
    bgColor: 'bg-green-500/10',
  },
  {
    name: 'API Credits',
    value: '-',
    icon: Zap,
    change: 'Add API key to check',
    color: 'text-amber-500',
    bgColor: 'bg-amber-500/10',
  },
];

const recentProjects: {
  id: number;
  title: string;
  status: 'draft' | 'processing' | 'completed' | 'failed';
  updatedAt: string;
}[] = [];

const statusConfig = {
  draft: { icon: Clock, color: 'text-muted-foreground', bg: 'bg-secondary' },
  processing: { icon: Play, color: 'text-blue-500', bg: 'bg-blue-500/10' },
  completed: { icon: CheckCircle2, color: 'text-green-500', bg: 'bg-green-500/10' },
  failed: { icon: AlertCircle, color: 'text-destructive', bg: 'bg-destructive/10' },
};

export default function Dashboard() {
  const { user } = useAuth();

  return (
    <DashboardLayout>
      {/* Welcome section */}
      <div className="mb-8">
        <h1 className="text-2xl font-bold text-foreground">
          Welcome back, {user?.name?.split(' ')[0]}!
        </h1>
        <p className="text-muted-foreground mt-1">
          Here's what's happening with your video projects
        </p>
      </div>

      {/* Stats grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        {stats.map((stat) => (
          <div
            key={stat.name}
            className="bg-card rounded-xl border border-border p-6 hover:shadow-md transition-shadow"
          >
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm text-muted-foreground">{stat.name}</p>
                <p className="text-3xl font-bold text-foreground mt-1">{stat.value}</p>
                <p className="text-xs text-muted-foreground mt-2">{stat.change}</p>
              </div>
              <div className={`p-3 rounded-lg ${stat.bgColor}`}>
                <stat.icon className={stat.color} size={24} />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Quick actions */}
      <div className="bg-gradient-to-r from-primary/10 via-primary/5 to-transparent rounded-xl border border-primary/20 p-6 mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 className="text-lg font-semibold text-foreground">Create Your First Video</h2>
            <p className="text-sm text-muted-foreground mt-1">
              Use AI to generate music videos automatically
            </p>
          </div>
          <Link
            to="/projects/new"
            className="inline-flex items-center gap-2 bg-primary text-primary-foreground px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition-colors"
          >
            <Plus size={18} />
            New Project
          </Link>
        </div>
      </div>

      {/* Recent projects & Quick links */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent projects */}
        <div className="lg:col-span-2 bg-card rounded-xl border border-border">
          <div className="px-6 py-4 border-b border-border flex items-center justify-between">
            <h2 className="font-semibold text-foreground">Recent Projects</h2>
            <Link
              to="/projects"
              className="text-sm text-primary hover:underline flex items-center gap-1"
            >
              View all <ArrowRight size={14} />
            </Link>
          </div>
          <div className="p-6">
            {recentProjects.length === 0 ? (
              <div className="text-center py-8">
                <FolderKanban className="mx-auto text-muted-foreground/50" size={48} />
                <p className="text-muted-foreground mt-4">No projects yet</p>
                <Link
                  to="/projects/new"
                  className="inline-flex items-center gap-2 text-primary hover:underline mt-2 text-sm"
                >
                  <Plus size={14} />
                  Create your first project
                </Link>
              </div>
            ) : (
              <div className="space-y-3">
                {recentProjects.map((project) => {
                  const config = statusConfig[project.status];
                  return (
                    <Link
                      key={project.id}
                      to={`/projects/${project.id}`}
                      className="flex items-center justify-between p-3 rounded-lg hover:bg-secondary transition-colors"
                    >
                      <div className="flex items-center gap-3">
                        <div className={`p-2 rounded-lg ${config.bg}`}>
                          <config.icon className={config.color} size={16} />
                        </div>
                        <div>
                          <p className="font-medium text-foreground">{project.title}</p>
                          <p className="text-xs text-muted-foreground">{project.updatedAt}</p>
                        </div>
                      </div>
                      <span
                        className={`text-xs font-medium px-2 py-1 rounded-full ${config.bg} ${config.color}`}
                      >
                        {project.status}
                      </span>
                    </Link>
                  );
                })}
              </div>
            )}
          </div>
        </div>

        {/* Quick links */}
        <div className="bg-card rounded-xl border border-border">
          <div className="px-6 py-4 border-b border-border">
            <h2 className="font-semibold text-foreground">Quick Links</h2>
          </div>
          <div className="p-4 space-y-2">
            <Link
              to="/channels/new"
              className="flex items-center gap-3 p-3 rounded-lg hover:bg-secondary transition-colors group"
            >
              <div className="p-2 rounded-lg bg-green-500/10 group-hover:bg-green-500/20 transition-colors">
                <Tv2 className="text-green-500" size={18} />
              </div>
              <div>
                <p className="font-medium text-foreground">Add Channel</p>
                <p className="text-xs text-muted-foreground">Set up auto-publish</p>
              </div>
            </Link>
            <Link
              to="/settings/api-keys"
              className="flex items-center gap-3 p-3 rounded-lg hover:bg-secondary transition-colors group"
            >
              <div className="p-2 rounded-lg bg-amber-500/10 group-hover:bg-amber-500/20 transition-colors">
                <Zap className="text-amber-500" size={18} />
              </div>
              <div>
                <p className="font-medium text-foreground">Manage API Keys</p>
                <p className="text-xs text-muted-foreground">Connect kie.ai & R2</p>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
