import { useState, useEffect, useCallback, useRef } from 'react';
import { DashboardLayout } from '@/components/layout';
import { Link, useNavigate } from 'react-router-dom';
import { api } from '@/lib/api';
import type { Project } from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Plus,
  Search,
  MoreVertical,
  Clock,
  CheckCircle2,
  AlertCircle,
  Play,
  FolderKanban,
  Trash2,
  Eye,
  Edit3,
  Loader2,
} from 'lucide-react';

const statusConfig = {
  draft: { icon: Clock, label: 'Draft', color: 'text-muted-foreground', bg: 'bg-secondary' },
  processing: { icon: Play, label: 'Processing', color: 'text-blue-500', bg: 'bg-blue-500/10' },
  completed: { icon: CheckCircle2, label: 'Completed', color: 'text-green-500', bg: 'bg-green-500/10' },
  failed: { icon: AlertCircle, label: 'Failed', color: 'text-destructive', bg: 'bg-destructive/10' },
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

export default function Projects() {
  const navigate = useNavigate();
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [showDropdown, setShowDropdown] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<number | null>(null);
  const dropdownRef = useRef<HTMLDivElement>(null);

  const fetchProjects = useCallback(async () => {
    try {
      const params: Record<string, string> = { page: '1' };
      if (statusFilter !== 'all') {
        params.status = statusFilter;
      }
      const response = await api.projects.list(params);
      setProjects(response.data);
    } catch (err) {
      console.error('Failed to fetch projects:', err);
    } finally {
      setLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    fetchProjects();
  }, [fetchProjects]);

  // Close dropdown when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target as Node)) {
        setShowDropdown(null);
      }
    }

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleDelete = async (projectId: number) => {
    if (!confirm('Are you sure you want to delete this project?')) return;

    setDeleting(projectId);
    try {
      await api.projects.delete(projectId);
      setProjects((prev) => prev.filter((p) => p.id !== projectId));
    } catch (err) {
      console.error('Failed to delete project:', err);
      alert('Failed to delete project');
    } finally {
      setDeleting(null);
      setShowDropdown(null);
    }
  };

  const filteredProjects = projects.filter((project) => {
    const matchesSearch = project.title.toLowerCase().includes(searchQuery.toLowerCase());
    return matchesSearch;
  });

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
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Projects</h1>
          <p className="text-muted-foreground mt-1">Manage your video generation projects</p>
        </div>
        <Button asChild>
          <Link to="/projects/new">
            <Plus className="h-4 w-4 mr-2" />
            New Project
          </Link>
        </Button>
      </div>

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-4 mb-6">
        <div className="relative flex-1">
          <Search
            className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
            size={18}
          />
          <Input
            type="text"
            placeholder="Search projects..."
            value={searchQuery}
            onChange={(e: React.ChangeEvent<HTMLInputElement>) => setSearchQuery(e.target.value)}
            className="pl-10"
          />
        </div>
        <Select value={statusFilter} onValueChange={setStatusFilter}>
          <SelectTrigger className="w-[180px]">
            <SelectValue placeholder="Filter by status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All Status</SelectItem>
            <SelectItem value="draft">Draft</SelectItem>
            <SelectItem value="processing">Processing</SelectItem>
            <SelectItem value="completed">Completed</SelectItem>
            <SelectItem value="failed">Failed</SelectItem>
          </SelectContent>
        </Select>
      </div>

      {/* Projects list */}
      {filteredProjects.length === 0 ? (
        <div className="bg-card rounded-xl border border-border p-12 text-center">
          <FolderKanban className="mx-auto text-muted-foreground/50" size={64} />
          <h3 className="text-lg font-semibold text-foreground mt-4">
            {projects.length === 0 ? 'No projects yet' : 'No matching projects'}
          </h3>
          <p className="text-muted-foreground mt-2 max-w-sm mx-auto">
            {projects.length === 0
              ? 'Create your first project to start generating AI-powered music videos'
              : 'Try adjusting your search or filter criteria'}
          </p>
          {projects.length === 0 && (
            <Button asChild className="mt-6">
              <Link to="/projects/new">
                <Plus className="h-4 w-4 mr-2" />
                Create First Project
              </Link>
            </Button>
          )}
        </div>
      ) : (
        <div className="bg-card rounded-xl border border-border overflow-hidden">
          <table className="w-full">
            <thead>
              <tr className="border-b border-border bg-secondary/30">
                <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">
                  Project
                </th>
                <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground hidden md:table-cell">
                  Channel
                </th>
                <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground">
                  Status
                </th>
                <th className="text-left py-3 px-4 text-sm font-medium text-muted-foreground hidden sm:table-cell">
                  Updated
                </th>
                <th className="text-right py-3 px-4 text-sm font-medium text-muted-foreground">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody>
              {filteredProjects.map((project) => {
                const config = statusConfig[project.status];
                return (
                  <tr
                    key={project.id}
                    className="border-b border-border last:border-0 hover:bg-secondary/20 transition-colors cursor-pointer"
                    onClick={() => navigate(`/projects/${project.id}`)}
                  >
                    <td className="py-4 px-4">
                      <div>
                        <p className="font-medium text-foreground">{project.title}</p>
                        <p className="text-sm text-muted-foreground line-clamp-1">
                          {project.description || 'No description'}
                        </p>
                      </div>
                    </td>
                    <td className="py-4 px-4 hidden md:table-cell">
                      <span className="text-sm text-muted-foreground">
                        {project.channel?.name || '-'}
                      </span>
                    </td>
                    <td className="py-4 px-4">
                      <span
                        className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${config.bg} ${config.color}`}
                      >
                        <config.icon size={12} />
                        {config.label}
                      </span>
                    </td>
                    <td className="py-4 px-4 hidden sm:table-cell">
                      <span className="text-sm text-muted-foreground">
                        {formatRelativeTime(project.updated_at)}
                      </span>
                    </td>
                    <td className="py-4 px-4 text-right" onClick={(e) => e.stopPropagation()}>
                      <div className="relative inline-block" ref={showDropdown === project.id ? dropdownRef : null}>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={(e) => {
                            e.stopPropagation();
                            setShowDropdown(showDropdown === project.id ? null : project.id);
                          }}
                        >
                          <MoreVertical size={16} />
                        </Button>
                        {showDropdown === project.id && (
                          <div className="absolute right-0 top-full mt-1 w-40 bg-card rounded-lg border border-border shadow-lg py-1 z-10">
                            <Link
                              to={`/projects/${project.id}`}
                              className="flex items-center gap-2 px-3 py-2 text-sm text-foreground hover:bg-secondary transition-colors"
                            >
                              <Eye size={14} />
                              View
                            </Link>
                            <Link
                              to={`/projects/${project.id}/edit`}
                              className="flex items-center gap-2 px-3 py-2 text-sm text-foreground hover:bg-secondary transition-colors"
                            >
                              <Edit3 size={14} />
                              Edit
                            </Link>
                            <button
                              onClick={() => handleDelete(project.id)}
                              disabled={deleting === project.id}
                              className="w-full flex items-center gap-2 px-3 py-2 text-sm text-destructive hover:bg-secondary transition-colors disabled:opacity-50"
                            >
                              {deleting === project.id ? (
                                <Loader2 size={14} className="animate-spin" />
                              ) : (
                                <Trash2 size={14} />
                              )}
                              Delete
                            </button>
                          </div>
                        )}
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </DashboardLayout>
  );
}
