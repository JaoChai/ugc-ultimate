import { useState, useEffect, useCallback } from 'react';
import { DashboardLayout } from '@/components/layout';
import { useAuth } from '@/contexts/AuthContext';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import type { Project, Channel } from '@/lib/api';
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
  Loader2,
} from 'lucide-react';

const statusConfig = {
  draft: { icon: Clock, color: 'text-muted-foreground', bg: 'bg-secondary' },
  processing: { icon: Play, color: 'text-blue-500', bg: 'bg-blue-500/10' },
  completed: { icon: CheckCircle2, color: 'text-green-500', bg: 'bg-green-500/10' },
  failed: { icon: AlertCircle, color: 'text-destructive', bg: 'bg-destructive/10' },
};

function formatRelativeTime(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

  if (diffInSeconds < 60) return 'Just now';
  if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
  if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
  if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
  return date.toLocaleDateString();
}

export default function Dashboard() {
  const { user } = useAuth();
  const [loading, setLoading] = useState(true);
  const [projects, setProjects] = useState<Project[]>([]);
  const [channels, setChannels] = useState<Channel[]>([]);
  const [stats, setStats] = useState({
    totalProjects: 0,
    processingProjects: 0,
    completedThisWeek: 0,
    activeChannels: 0,
    scheduledChannels: 0,
  });

  const fetchData = useCallback(async () => {
    try {
      const [projectsRes, channelsRes] = await Promise.all([
        api.projects.list({ page: '1' }),
        api.channels.list(),
      ]);

      const allProjects = projectsRes.data;
      const allChannels = channelsRes.channels;

      setProjects(allProjects.slice(0, 5)); // Recent 5 projects
      setChannels(allChannels);

      // Calculate stats
      const now = new Date();
      const weekAgo = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);

      const completedThisWeek = allProjects.filter(
        (p) => p.status === 'completed' && new Date(p.completed_at || p.updated_at) >= weekAgo
      ).length;

      const processingProjects = allProjects.filter((p) => p.status === 'processing').length;
      const activeChannels = allChannels.filter((c) => c.is_active).length;
      const scheduledChannels = allChannels.filter((c) => c.schedule_config?.enabled).length;

      setStats({
        totalProjects: projectsRes.total,
        processingProjects,
        completedThisWeek,
        activeChannels,
        scheduledChannels,
      });
    } catch (err) {
      console.error('Failed to fetch dashboard data:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchData();
  }, [fetchData]);

  const statsCards = [
    {
      name: 'Total Projects',
      value: stats.totalProjects.toString(),
      icon: FolderKanban,
      change: stats.processingProjects > 0
        ? `${stats.processingProjects} processing`
        : `+${stats.completedThisWeek} this week`,
      color: 'text-blue-500',
      bgColor: 'bg-blue-500/10',
    },
    {
      name: 'Active Channels',
      value: stats.activeChannels.toString(),
      icon: Tv2,
      change: `${stats.scheduledChannels} scheduled`,
      color: 'text-green-500',
      bgColor: 'bg-green-500/10',
    },
    {
      name: 'API Credits',
      value: '-',
      icon: Zap,
      change: 'Check in API Keys',
      color: 'text-amber-500',
      bgColor: 'bg-amber-500/10',
    },
  ];

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-64">
          <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
        </div>
      </DashboardLayout>
    );
  }

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
        {statsCards.map((stat) => (
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
            <h2 className="text-lg font-semibold text-foreground">
              {projects.length === 0 ? 'Create Your First Video' : 'Start a New Project'}
            </h2>
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
            {projects.length === 0 ? (
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
                {projects.map((project) => {
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
                          <p className="text-xs text-muted-foreground">
                            {formatRelativeTime(project.updated_at)}
                          </p>
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
              to="/channels"
              className="flex items-center gap-3 p-3 rounded-lg hover:bg-secondary transition-colors group"
            >
              <div className="p-2 rounded-lg bg-green-500/10 group-hover:bg-green-500/20 transition-colors">
                <Tv2 className="text-green-500" size={18} />
              </div>
              <div>
                <p className="font-medium text-foreground">
                  {channels.length === 0 ? 'Add Channel' : 'Manage Channels'}
                </p>
                <p className="text-xs text-muted-foreground">
                  {channels.length === 0 ? 'Set up auto-publish' : `${channels.length} channel${channels.length !== 1 ? 's' : ''}`}
                </p>
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
