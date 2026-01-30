import { useState, useEffect, useCallback } from 'react';
import { DashboardLayout } from '@/components/layout';
import { useAuth } from '@/contexts/AuthContext';
import { Link } from 'react-router-dom';
import { api } from '@/lib/api';
import type { Project, Channel } from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import {
  FolderKanban,
  Tv2,
  Zap,
  Plus,
  ArrowRight,
  Loader2,
} from 'lucide-react';

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

      setProjects(allProjects.slice(0, 5));
      setChannels(allChannels);

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
    },
    {
      name: 'Active Channels',
      value: stats.activeChannels.toString(),
      icon: Tv2,
      change: `${stats.scheduledChannels} scheduled`,
    },
    {
      name: 'API Credits',
      value: '-',
      icon: Zap,
      change: 'Check in API Keys',
    },
  ];

  if (loading) {
    return (
      <DashboardLayout>
        <div className="flex items-center justify-center h-64">
          <Loader2 className="h-8 w-8 animate-spin text-slate-400" />
        </div>
      </DashboardLayout>
    );
  }

  return (
    <DashboardLayout>
      {/* Welcome section */}
      <div className="mb-8">
        <h1 className="text-2xl font-semibold text-slate-900">
          Welcome back, {user?.name?.split(' ')[0]}
        </h1>
        <p className="text-slate-500 mt-1">
          Here's what's happening with your video projects
        </p>
      </div>

      {/* Stats grid */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        {statsCards.map((stat) => (
          <div
            key={stat.name}
            className="bg-white rounded-lg border border-slate-200 p-5 hover:border-slate-300 transition-colors"
          >
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm text-slate-500">{stat.name}</p>
                <p className="text-2xl font-semibold text-slate-900 mt-1">{stat.value}</p>
                <p className="text-xs text-slate-400 mt-2">{stat.change}</p>
              </div>
              <div className="p-2 rounded-lg bg-slate-50">
                <stat.icon className="text-slate-500" size={20} />
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Quick action */}
      <div className="bg-white rounded-lg border border-slate-200 p-5 mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h2 className="text-base font-medium text-slate-900">
              {projects.length === 0 ? 'Create Your First Video' : 'Start a New Project'}
            </h2>
            <p className="text-sm text-slate-500 mt-1">
              Use AI to generate music videos automatically
            </p>
          </div>
          <Link
            to="/projects/new"
            className="inline-flex items-center gap-2 bg-slate-900 text-white px-4 py-2.5 rounded-lg font-medium hover:bg-slate-800 transition-colors cursor-pointer"
          >
            <Plus size={18} />
            New Project
          </Link>
        </div>
      </div>

      {/* Recent projects & Quick links */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent projects */}
        <div className="lg:col-span-2 bg-white rounded-lg border border-slate-200">
          <div className="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h2 className="font-medium text-slate-900">Recent Projects</h2>
            <Link
              to="/projects"
              className="text-sm text-slate-500 hover:text-slate-900 flex items-center gap-1 cursor-pointer"
            >
              View all <ArrowRight size={14} />
            </Link>
          </div>
          <div className="p-5">
            {projects.length === 0 ? (
              <div className="text-center py-8">
                <FolderKanban className="mx-auto text-slate-300" size={48} />
                <p className="text-slate-500 mt-4">No projects yet</p>
                <Link
                  to="/projects/new"
                  className="inline-flex items-center gap-2 text-slate-900 hover:underline mt-2 text-sm cursor-pointer"
                >
                  <Plus size={14} />
                  Create your first project
                </Link>
              </div>
            ) : (
              <div className="space-y-2">
                {projects.map((project) => (
                  <Link
                    key={project.id}
                    to={`/projects/${project.id}`}
                    className="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
                  >
                    <div className="flex items-center gap-3">
                      <div className="p-2 rounded-lg bg-slate-50">
                        <FolderKanban className="text-slate-500" size={16} />
                      </div>
                      <div>
                        <p className="font-medium text-slate-900">{project.title}</p>
                        <p className="text-xs text-slate-400">
                          {formatRelativeTime(project.updated_at)}
                        </p>
                      </div>
                    </div>
                    <Badge status={project.status as 'draft' | 'processing' | 'completed' | 'failed'}>
                      {project.status}
                    </Badge>
                  </Link>
                ))}
              </div>
            )}
          </div>
        </div>

        {/* Quick links */}
        <div className="bg-white rounded-lg border border-slate-200">
          <div className="px-5 py-4 border-b border-slate-200">
            <h2 className="font-medium text-slate-900">Quick Links</h2>
          </div>
          <div className="p-4 space-y-1">
            <Link
              to="/channels"
              className="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
            >
              <div className="p-2 rounded-lg bg-slate-50">
                <Tv2 className="text-slate-500" size={18} />
              </div>
              <div>
                <p className="font-medium text-slate-900">
                  {channels.length === 0 ? 'Add Channel' : 'Manage Channels'}
                </p>
                <p className="text-xs text-slate-400">
                  {channels.length === 0 ? 'Set up auto-publish' : `${channels.length} channel${channels.length !== 1 ? 's' : ''}`}
                </p>
              </div>
            </Link>
            <Link
              to="/settings/api-keys"
              className="flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer"
            >
              <div className="p-2 rounded-lg bg-slate-50">
                <Zap className="text-slate-500" size={18} />
              </div>
              <div>
                <p className="font-medium text-slate-900">Manage API Keys</p>
                <p className="text-xs text-slate-400">Connect kie.ai & R2</p>
              </div>
            </Link>
          </div>
        </div>
      </div>
    </DashboardLayout>
  );
}
