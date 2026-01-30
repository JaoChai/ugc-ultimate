import { useState, useEffect, useCallback } from 'react';
import { DashboardLayout } from '@/components/layout/DashboardLayout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { api } from '@/lib/api';
import type { Channel } from '@/lib/api';
import {
  Plus,
  Tv2,
  MoreVertical,
  Calendar,
  Youtube,
  Instagram,
  Settings,
  Trash2,
  Loader2,
  Video,
  Clock,
} from 'lucide-react';

const platformConfig: Record<string, { icon: React.ElementType; label: string; color: string; bg: string }> = {
  youtube: { icon: Youtube, label: 'YouTube', color: 'text-red-500', bg: 'bg-red-500/10' },
  tiktok: { icon: Tv2, label: 'TikTok', color: 'text-foreground', bg: 'bg-secondary' },
  instagram: { icon: Instagram, label: 'Instagram', color: 'text-pink-500', bg: 'bg-pink-500/10' },
};

interface ChannelWithCount extends Channel {
  projects_count?: number;
}

export default function Channels() {
  const [channels, setChannels] = useState<ChannelWithCount[]>([]);
  const [loading, setLoading] = useState(true);
  const [showDropdown, setShowDropdown] = useState<number | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [showScheduleModal, setShowScheduleModal] = useState(false);
  const [editingChannel, setEditingChannel] = useState<ChannelWithCount | null>(null);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const [formData, setFormData] = useState({
    name: '',
    platform: 'youtube',
    description: '',
  });

  const [scheduleData, setScheduleData] = useState({
    enabled: false,
    cron: '0 9 * * *',
    theme: '',
    duration: 60,
    language: 'English',
    visual_style: 'cinematic',
  });

  const fetchChannels = useCallback(async () => {
    try {
      const data = await api.channels.list();
      setChannels(data.channels);
    } catch (err) {
      console.error('Failed to fetch channels:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchChannels();
  }, [fetchChannels]);

  const handleCreate = () => {
    setEditingChannel(null);
    setFormData({ name: '', platform: 'youtube', description: '' });
    setShowModal(true);
  };

  const handleEdit = (channel: ChannelWithCount) => {
    setEditingChannel(channel);
    setFormData({
      name: channel.name,
      platform: channel.platform || 'youtube',
      description: channel.description || '',
    });
    setShowModal(true);
    setShowDropdown(null);
  };

  const handleSchedule = (channel: ChannelWithCount) => {
    setEditingChannel(channel);
    const config = channel.schedule_config || {};
    setScheduleData({
      enabled: config.enabled || false,
      cron: config.cron || '0 9 * * *',
      theme: config.theme || '',
      duration: config.duration || 60,
      language: config.language || 'English',
      visual_style: config.visual_style || 'cinematic',
    });
    setShowScheduleModal(true);
    setShowDropdown(null);
  };

  const handleSave = async () => {
    setSaving(true);
    setError('');

    try {
      if (editingChannel) {
        await api.channels.update(editingChannel.id, formData);
      } else {
        await api.channels.create(formData);
      }
      await fetchChannels();
      setShowModal(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save channel');
    } finally {
      setSaving(false);
    }
  };

  const handleSaveSchedule = async () => {
    if (!editingChannel) return;
    setSaving(true);
    setError('');

    try {
      await api.put(`/channels/${editingChannel.id}/schedule`, {
        schedule_config: scheduleData,
      });
      await fetchChannels();
      setShowScheduleModal(false);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to save schedule');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (channel: ChannelWithCount) => {
    if (!confirm(`Are you sure you want to delete "${channel.name}"?`)) return;

    try {
      await api.channels.delete(channel.id);
      await fetchChannels();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to delete channel');
    }
    setShowDropdown(null);
  };

  const handleToggleActive = async (channel: ChannelWithCount) => {
    try {
      await api.channels.update(channel.id, { is_active: !channel.is_active });
      await fetchChannels();
    } catch (err) {
      alert(err instanceof Error ? err.message : 'Failed to update channel');
    }
    setShowDropdown(null);
  };

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
          <h1 className="text-2xl font-bold">Channels</h1>
          <p className="text-muted-foreground mt-1">Manage your publishing channels</p>
        </div>
        <Button onClick={handleCreate}>
          <Plus className="h-4 w-4 mr-2" />
          Add Channel
        </Button>
      </div>

      {/* Channels grid */}
      {channels.length === 0 ? (
        <Card className="p-12 text-center">
          <Tv2 className="mx-auto text-muted-foreground/50 h-16 w-16" />
          <h3 className="text-lg font-semibold mt-4">No channels yet</h3>
          <p className="text-muted-foreground mt-2 max-w-sm mx-auto">
            Add your first channel to start scheduling automatic video generation
          </p>
          <Button onClick={handleCreate} className="mt-6">
            <Plus className="h-4 w-4 mr-2" />
            Add First Channel
          </Button>
        </Card>
      ) : (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {channels.map((channel) => {
            const config = platformConfig[channel.platform || 'youtube'] || platformConfig.youtube;
            const IconComponent = config.icon;
            return (
              <Card key={channel.id} className="hover:shadow-md transition-shadow">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between">
                    <div className={`p-3 rounded-lg ${config.bg}`}>
                      <IconComponent className={`h-6 w-6 ${config.color}`} />
                    </div>
                    <div className="relative">
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setShowDropdown(showDropdown === channel.id ? null : channel.id)}
                      >
                        <MoreVertical className="h-4 w-4" />
                      </Button>
                      {showDropdown === channel.id && (
                        <div className="absolute right-0 top-full mt-1 w-44 bg-card rounded-lg border shadow-lg py-1 z-10">
                          <button
                            onClick={() => handleEdit(channel)}
                            className="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-secondary"
                          >
                            <Settings className="h-4 w-4" />
                            Edit Settings
                          </button>
                          <button
                            onClick={() => handleSchedule(channel)}
                            className="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-secondary"
                          >
                            <Clock className="h-4 w-4" />
                            Configure Schedule
                          </button>
                          <button
                            onClick={() => handleToggleActive(channel)}
                            className="w-full flex items-center gap-2 px-3 py-2 text-sm hover:bg-secondary"
                          >
                            <Video className="h-4 w-4" />
                            {channel.is_active ? 'Pause' : 'Activate'}
                          </button>
                          <hr className="my-1" />
                          <button
                            onClick={() => handleDelete(channel)}
                            className="w-full flex items-center gap-2 px-3 py-2 text-sm text-destructive hover:bg-secondary"
                          >
                            <Trash2 className="h-4 w-4" />
                            Delete
                          </button>
                        </div>
                      )}
                    </div>
                  </div>
                  <CardTitle className="text-lg mt-3">{channel.name}</CardTitle>
                  <CardDescription className="line-clamp-2">
                    {channel.description || 'No description'}
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center gap-4 pt-2 border-t">
                    <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                      <Calendar className="h-4 w-4" />
                      {channel.schedule_config?.enabled
                        ? channel.schedule_config?.cron || 'Scheduled'
                        : 'No schedule'}
                    </div>
                    <Badge
                      variant={channel.is_active ? 'default' : 'secondary'}
                      className="ml-auto"
                    >
                      {channel.is_active ? 'Active' : 'Paused'}
                    </Badge>
                  </div>
                  <div className="mt-3 text-sm text-muted-foreground">
                    {channel.projects_count || 0} project{(channel.projects_count || 0) !== 1 ? 's' : ''}
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>
      )}

      {/* Create/Edit Modal */}
      <Dialog open={showModal} onOpenChange={setShowModal}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingChannel ? 'Edit Channel' : 'Create Channel'}</DialogTitle>
            <DialogDescription>
              {editingChannel ? 'Update channel settings' : 'Add a new publishing channel'}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            {error && (
              <div className="p-3 bg-destructive/10 text-destructive text-sm rounded-lg">
                {error}
              </div>
            )}
            <div className="space-y-2">
              <Label htmlFor="name">Channel Name</Label>
              <Input
                id="name"
                value={formData.name}
                onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                  setFormData({ ...formData, name: e.target.value })
                }
                placeholder="My YouTube Channel"
              />
            </div>
            <div className="space-y-2">
              <Label>Platform</Label>
              <Select
                value={formData.platform}
                onValueChange={(v: string) => setFormData({ ...formData, platform: v })}
              >
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="youtube">YouTube</SelectItem>
                  <SelectItem value="tiktok">TikTok</SelectItem>
                  <SelectItem value="instagram">Instagram</SelectItem>
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="description">Description</Label>
              <Textarea
                id="description"
                value={formData.description}
                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                  setFormData({ ...formData, description: e.target.value })
                }
                placeholder="What kind of content will this channel have?"
                rows={3}
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowModal(false)}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={saving || !formData.name}>
              {saving && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              {editingChannel ? 'Save Changes' : 'Create Channel'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Schedule Modal */}
      <Dialog open={showScheduleModal} onOpenChange={setShowScheduleModal}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Configure Schedule</DialogTitle>
            <DialogDescription>
              Set up automatic content generation for {editingChannel?.name}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            {error && (
              <div className="p-3 bg-destructive/10 text-destructive text-sm rounded-lg">
                {error}
              </div>
            )}
            <div className="flex items-center justify-between">
              <div>
                <Label>Enable Scheduling</Label>
                <p className="text-sm text-muted-foreground">
                  Auto-generate content on schedule
                </p>
              </div>
              <Switch
                checked={scheduleData.enabled}
                onCheckedChange={(v: boolean) =>
                  setScheduleData({ ...scheduleData, enabled: v })
                }
              />
            </div>

            {scheduleData.enabled && (
              <>
                <div className="space-y-2">
                  <Label>Schedule (Cron Expression)</Label>
                  <Select
                    value={scheduleData.cron}
                    onValueChange={(v: string) =>
                      setScheduleData({ ...scheduleData, cron: v })
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="0 9 * * *">Daily at 9:00 AM</SelectItem>
                      <SelectItem value="0 9 * * 1-5">Weekdays at 9:00 AM</SelectItem>
                      <SelectItem value="0 9 * * 1,3,5">Mon, Wed, Fri at 9:00 AM</SelectItem>
                      <SelectItem value="0 9 * * 0">Weekly on Sunday</SelectItem>
                      <SelectItem value="0 9 1 * *">Monthly on the 1st</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label>Default Theme</Label>
                  <Input
                    value={scheduleData.theme}
                    onChange={(e: React.ChangeEvent<HTMLInputElement>) =>
                      setScheduleData({ ...scheduleData, theme: e.target.value })
                    }
                    placeholder="e.g., Relaxing lofi music for studying"
                  />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Duration (seconds)</Label>
                    <Select
                      value={scheduleData.duration.toString()}
                      onValueChange={(v: string) =>
                        setScheduleData({ ...scheduleData, duration: parseInt(v) })
                      }
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="30">30s</SelectItem>
                        <SelectItem value="60">60s</SelectItem>
                        <SelectItem value="90">90s</SelectItem>
                        <SelectItem value="120">120s</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-2">
                    <Label>Language</Label>
                    <Select
                      value={scheduleData.language}
                      onValueChange={(v: string) =>
                        setScheduleData({ ...scheduleData, language: v })
                      }
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="English">English</SelectItem>
                        <SelectItem value="Thai">Thai</SelectItem>
                        <SelectItem value="Japanese">Japanese</SelectItem>
                        <SelectItem value="Korean">Korean</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                </div>

                <div className="space-y-2">
                  <Label>Visual Style</Label>
                  <Select
                    value={scheduleData.visual_style}
                    onValueChange={(v: string) =>
                      setScheduleData({ ...scheduleData, visual_style: v })
                    }
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="cinematic">Cinematic</SelectItem>
                      <SelectItem value="anime">Anime</SelectItem>
                      <SelectItem value="realistic">Realistic</SelectItem>
                      <SelectItem value="3d">3D Render</SelectItem>
                      <SelectItem value="cyberpunk">Cyberpunk</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setShowScheduleModal(false)}>
              Cancel
            </Button>
            <Button onClick={handleSaveSchedule} disabled={saving}>
              {saving && <Loader2 className="h-4 w-4 mr-2 animate-spin" />}
              Save Schedule
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </DashboardLayout>
  );
}
