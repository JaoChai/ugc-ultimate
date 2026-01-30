import { DashboardLayout } from '@/components/layout';
import { Link } from 'react-router-dom';
import {
  Plus,
  Search,
  Filter,
  MoreVertical,
  Clock,
  CheckCircle2,
  AlertCircle,
  Play,
  FolderKanban,
  Trash2,
  Eye,
  Edit3,
} from 'lucide-react';
import { useState } from 'react';

interface Project {
  id: number;
  title: string;
  description: string;
  status: 'draft' | 'processing' | 'completed' | 'failed';
  channel: string | null;
  createdAt: string;
  updatedAt: string;
}

const statusConfig = {
  draft: { icon: Clock, label: 'Draft', color: 'text-muted-foreground', bg: 'bg-secondary' },
  processing: { icon: Play, label: 'Processing', color: 'text-blue-500', bg: 'bg-blue-500/10' },
  completed: { icon: CheckCircle2, label: 'Completed', color: 'text-green-500', bg: 'bg-green-500/10' },
  failed: { icon: AlertCircle, label: 'Failed', color: 'text-destructive', bg: 'bg-destructive/10' },
};

const mockProjects: Project[] = [];

export default function Projects() {
  const [searchQuery, setSearchQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [showDropdown, setShowDropdown] = useState<number | null>(null);

  const filteredProjects = mockProjects.filter((project) => {
    const matchesSearch = project.title.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesStatus = statusFilter === 'all' || project.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  return (
    <DashboardLayout>
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-foreground">Projects</h1>
          <p className="text-muted-foreground mt-1">Manage your video generation projects</p>
        </div>
        <Link
          to="/projects/new"
          className="inline-flex items-center gap-2 bg-primary text-primary-foreground px-4 py-2 rounded-lg font-medium hover:bg-primary/90 transition-colors"
        >
          <Plus size={18} />
          New Project
        </Link>
      </div>

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-4 mb-6">
        <div className="relative flex-1">
          <Search
            className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
            size={18}
          />
          <input
            type="text"
            placeholder="Search projects..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-10 pr-4 py-2 bg-card border border-border rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent"
          />
        </div>
        <div className="relative">
          <Filter
            className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
            size={18}
          />
          <select
            value={statusFilter}
            onChange={(e) => setStatusFilter(e.target.value)}
            className="pl-10 pr-8 py-2 bg-card border border-border rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent appearance-none cursor-pointer"
          >
            <option value="all">All Status</option>
            <option value="draft">Draft</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="failed">Failed</option>
          </select>
        </div>
      </div>

      {/* Projects list */}
      {filteredProjects.length === 0 ? (
        <div className="bg-card rounded-xl border border-border p-12 text-center">
          <FolderKanban className="mx-auto text-muted-foreground/50" size={64} />
          <h3 className="text-lg font-semibold text-foreground mt-4">No projects yet</h3>
          <p className="text-muted-foreground mt-2 max-w-sm mx-auto">
            Create your first project to start generating AI-powered music videos
          </p>
          <Link
            to="/projects/new"
            className="inline-flex items-center gap-2 bg-primary text-primary-foreground px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition-colors mt-6"
          >
            <Plus size={18} />
            Create First Project
          </Link>
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
                    className="border-b border-border last:border-0 hover:bg-secondary/20 transition-colors"
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
                        {project.channel || '-'}
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
                      <span className="text-sm text-muted-foreground">{project.updatedAt}</span>
                    </td>
                    <td className="py-4 px-4 text-right">
                      <div className="relative inline-block">
                        <button
                          onClick={() =>
                            setShowDropdown(showDropdown === project.id ? null : project.id)
                          }
                          className="p-2 rounded-lg hover:bg-secondary text-muted-foreground hover:text-foreground transition-colors"
                        >
                          <MoreVertical size={16} />
                        </button>
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
                            <button className="w-full flex items-center gap-2 px-3 py-2 text-sm text-destructive hover:bg-secondary transition-colors">
                              <Trash2 size={14} />
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
