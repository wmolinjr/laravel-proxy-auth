import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { adminRoutes } from '@/lib/admin-routes';
import { type BreadcrumbItem, type SystemSetting } from '@/types';
import { Head } from '@inertiajs/react';
import { 
  Settings, 
  Shield, 
  Database, 
  Globe, 
  Lock,
  Save,
  RotateCcw
} from 'lucide-react';
import { useState } from 'react';

interface SystemSettingsProps {
  settings: Record<string, SystemSetting[]>;
}

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'Settings', href: adminRoutes.settings.index() },
];

const categoryIcons = {
  general: Globe,
  security: Shield,
  oauth: Lock,
  system: Database,
};

const categoryTitles = {
  general: 'General Settings',
  security: 'Security Settings', 
  oauth: 'OAuth Configuration',
  system: 'System Settings',
};

export default function SystemSettings({ settings }: SystemSettingsProps) {
  const [formData, setFormData] = useState<Record<string, unknown>>({});
  const [changedSettings, setChangedSettings] = useState<Set<string>>(new Set());

  const handleSettingChange = (key: string, value: unknown, type: string) => {
    let processedValue = value;
    
    // Process value based on type
    if (type === 'boolean') {
      processedValue = Boolean(value);
    } else if (type === 'integer') {
      processedValue = parseInt(String(value)) || 0;
    } else if (type === 'float') {
      processedValue = parseFloat(String(value)) || 0;
    }

    setFormData(prev => ({ ...prev, [key]: processedValue }));
    setChangedSettings(prev => new Set(prev).add(key));
  };

  const getSettingValue = (setting: SystemSetting) => {
    if (changedSettings.has(setting.key)) {
      return formData[setting.key];
    }
    return setting.value;
  };

  const handleSave = () => {
    // This would typically make a request to save the settings
    console.log('Saving settings:', formData);
    // Reset changed settings after save
    setChangedSettings(new Set());
  };

  const handleReset = () => {
    setFormData({});
    setChangedSettings(new Set());
  };

  const renderSettingInput = (setting: SystemSetting) => {
    const value = getSettingValue(setting);
    const isChanged = changedSettings.has(setting.key);

    switch (typeof setting.value) {
      case 'boolean':
        return (
          <div className="flex items-center space-x-2">
            <Switch
              id={setting.key}
              checked={Boolean(value)}
              onCheckedChange={(checked) => handleSettingChange(setting.key, checked, 'boolean')}
            />
            <Label htmlFor={setting.key} className="text-sm">
              {value ? 'Enabled' : 'Disabled'}
            </Label>
          </div>
        );

      case 'number':
        return (
          <Input
            type="number"
            value={String(value || '')}
            onChange={(e) => handleSettingChange(setting.key, e.target.value, 'integer')}
            className={isChanged ? 'border-orange-500' : ''}
          />
        );

      case 'string':
        if (setting.description?.toLowerCase().includes('description') || 
            setting.description?.toLowerCase().includes('notes')) {
          return (
            <Textarea
              value={String(value || '')}
              onChange={(e) => handleSettingChange(setting.key, e.target.value, 'string')}
              className={isChanged ? 'border-orange-500' : ''}
              rows={3}
            />
          );
        }
        return (
          <Input
            type="text"
            value={String(value || '')}
            onChange={(e) => handleSettingChange(setting.key, e.target.value, 'string')}
            className={isChanged ? 'border-orange-500' : ''}
          />
        );

      default:
        return (
          <Input
            type="text"
            value={JSON.stringify(value) || ''}
            onChange={(e) => {
              try {
                const parsed = JSON.parse(e.target.value);
                handleSettingChange(setting.key, parsed, 'object');
              } catch {
                handleSettingChange(setting.key, e.target.value, 'string');
              }
            }}
            className={isChanged ? 'border-orange-500' : ''}
          />
        );
    }
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="System Settings" />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">System Settings</h1>
            <p className="text-muted-foreground">
              Configure your authentication server settings
            </p>
          </div>
          
          <div className="flex items-center gap-2">
            {changedSettings.size > 0 && (
              <>
                <Button variant="outline" onClick={handleReset}>
                  <RotateCcw className="h-4 w-4 mr-2" />
                  Reset
                </Button>
                <Button onClick={handleSave}>
                  <Save className="h-4 w-4 mr-2" />
                  Save Changes ({changedSettings.size})
                </Button>
              </>
            )}
          </div>
        </div>

        {/* Settings by Category */}
        <div className="space-y-6">
          {Object.entries(settings).map(([category, categorySettings]) => {
            const IconComponent = categoryIcons[category as keyof typeof categoryIcons] || Settings;
            const title = categoryTitles[category as keyof typeof categoryTitles] || category;
            
            return (
              <Card key={category}>
                <CardHeader>
                  <CardTitle className="flex items-center gap-2">
                    <IconComponent className="h-5 w-5" />
                    {title}
                    <Badge variant="secondary" className="ml-auto">
                      {categorySettings.length} settings
                    </Badge>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-6">
                    {categorySettings.map((setting, index) => (
                      <div key={setting.key}>
                        <div className="space-y-2">
                          <div className="flex items-start justify-between">
                            <div className="space-y-1">
                              <Label htmlFor={setting.key} className="text-sm font-medium">
                                {setting.key}
                                {changedSettings.has(setting.key) && (
                                  <Badge variant="outline" className="ml-2 text-xs">
                                    Modified
                                  </Badge>
                                )}
                              </Label>
                              {setting.description && (
                                <p className="text-sm text-muted-foreground">
                                  {setting.description}
                                </p>
                              )}
                            </div>
                            <div className="flex items-center gap-2">
                              {setting.is_encrypted && (
                                <Badge variant="secondary" className="text-xs">
                                  <Lock className="h-3 w-3 mr-1" />
                                  Encrypted
                                </Badge>
                              )}
                              {setting.is_public && (
                                <Badge variant="outline" className="text-xs">
                                  Public
                                </Badge>
                              )}
                            </div>
                          </div>
                          
                          <div className="max-w-md">
                            {renderSettingInput(setting)}
                          </div>
                          
                          <div className="flex items-center gap-4 text-xs text-muted-foreground">
                            <span>Type: {typeof setting.value}</span>
                            <span>Updated: {setting.updated_at}</span>
                            {setting.updated_by && (
                              <span>By: {setting.updated_by.name}</span>
                            )}
                          </div>
                        </div>
                        
                        {index < categorySettings.length - 1 && (
                          <Separator className="mt-6" />
                        )}
                      </div>
                    ))}
                  </div>
                </CardContent>
              </Card>
            );
          })}
        </div>

        {/* Help Text */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Settings className="h-5 w-5" />
              Configuration Help
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <h4 className="font-medium">Security Settings</h4>
                <p className="text-sm text-muted-foreground">
                  Configure authentication requirements, session timeouts, and access controls.
                </p>
              </div>
              
              <div className="space-y-2">
                <h4 className="font-medium">OAuth Configuration</h4>
                <p className="text-sm text-muted-foreground">
                  Set token lifetimes, refresh token settings, and OAuth flow configurations.
                </p>
              </div>
              
              <div className="space-y-2">
                <h4 className="font-medium">System Settings</h4>
                <p className="text-sm text-muted-foreground">
                  General application settings, logging configuration, and maintenance options.
                </p>
              </div>
              
              <div className="space-y-2">
                <h4 className="font-medium">Important Notes</h4>
                <ul className="text-sm text-muted-foreground space-y-1">
                  <li>• Changes take effect immediately</li>
                  <li>• Encrypted settings are stored securely</li>
                  <li>• All changes are logged for audit</li>
                </ul>
              </div>
            </div>
          </CardContent>
        </Card>

        {changedSettings.size > 0 && (
          <div className="fixed bottom-6 right-6 bg-background border rounded-lg shadow-lg p-4">
            <div className="flex items-center gap-4">
              <div className="text-sm">
                <span className="font-medium">{changedSettings.size}</span> setting(s) modified
              </div>
              <div className="flex gap-2">
                <Button variant="outline" size="sm" onClick={handleReset}>
                  Reset
                </Button>
                <Button size="sm" onClick={handleSave}>
                  Save Changes
                </Button>
              </div>
            </div>
          </div>
        )}
      </div>
    </AppLayout>
  );
}