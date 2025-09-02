import React, { useState } from 'react'
import { Head, useForm, Link } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Checkbox } from '@/components/ui/checkbox'
import { Badge } from '@/components/ui/badge'
import AppLayout from '@/layouts/app-layout'
import { adminRoutes } from '@/lib/admin-routes'
import { type BreadcrumbItem } from '@/types'
import { ArrowLeft, Plus, X, Save, Key, Settings, Shield, Activity } from 'lucide-react'

interface OAuthClient {
  id: string
  name: string
  description: string
  redirect_uris: string[]
  grants: string[]
  scopes: string[]
  is_confidential: boolean
  environment: string
  health_check_enabled: boolean
  health_check_url: string
  health_check_interval: number
  max_concurrent_tokens: number
  rate_limit_per_minute: number
  contact_email: string
  website_url: string
}

interface Props {
  client: OAuthClient
  grants: string[]
  scopes: string[]
  environments: string[]
}

const getBreadcrumbs = (client: OAuthClient): BreadcrumbItem[] => [
  { title: 'Admin', href: adminRoutes.dashboard() },
  { title: 'OAuth Clients', href: adminRoutes.oauthClients.index() },
  { title: client.name, href: adminRoutes.oauthClients.show(client.id) },
  { title: 'Edit', href: adminRoutes.oauthClients.edit(client.id) },
]

interface FormData {
  name: string
  description: string
  redirect_uris: string[]
  grants: string[]
  scopes: string[]
  is_confidential: boolean
  environment: string
  health_check_enabled: boolean
  health_check_url: string
  health_check_interval: number
  max_concurrent_tokens: number
  rate_limit_per_minute: number
  contact_email: string
  website_url: string
}

export default function EditOAuthClient({ client, grants, scopes, environments }: Props) {
  const [redirectUriInput, setRedirectUriInput] = useState('')

  const { data, setData, put, processing, errors } = useForm<FormData>({
    name: client.name || '',
    description: client.description || '',
    redirect_uris: client.redirect_uris || [],
    grants: client.grants || ['authorization_code'],
    scopes: client.scopes || ['openid'],
    is_confidential: client.is_confidential ?? true,
    environment: client.environment || 'development',
    health_check_enabled: client.health_check_enabled ?? false,
    health_check_url: client.health_check_url || '',
    health_check_interval: client.health_check_interval || 300,
    max_concurrent_tokens: client.max_concurrent_tokens || 1000,
    rate_limit_per_minute: client.rate_limit_per_minute || 100,
    contact_email: client.contact_email || '',
    website_url: client.website_url || '',
  })

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault()
    put(adminRoutes.oauthClients.update(client.id))
  }

  const addRedirectUri = () => {
    if (redirectUriInput && !data.redirect_uris.includes(redirectUriInput)) {
      setData('redirect_uris', [...data.redirect_uris, redirectUriInput])
      setRedirectUriInput('')
    }
  }

  const removeRedirectUri = (uri: string) => {
    setData('redirect_uris', data.redirect_uris.filter(u => u !== uri))
  }

  const handleGrantChange = (grant: string, checked: boolean) => {
    if (checked) {
      setData('grants', [...data.grants, grant])
    } else {
      setData('grants', data.grants.filter(g => g !== grant))
    }
  }

  const handleScopeChange = (scope: string, checked: boolean) => {
    if (checked) {
      setData('scopes', [...data.scopes, scope])
    } else {
      setData('scopes', data.scopes.filter(s => s !== scope))
    }
  }

  const breadcrumbs = getBreadcrumbs(client)

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title={`Edit ${client.name}`} />
      
      <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-4">
            <Link href={adminRoutes.oauthClients.show(client.id)}>
              <Button variant="outline" size="icon">
                <ArrowLeft className="h-4 w-4" />
              </Button>
            </Link>
            <div>
              <h1 className="text-3xl font-bold tracking-tight">Edit OAuth Client</h1>
              <p className="text-muted-foreground">
                Update settings for {client.name}
              </p>
            </div>
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <div className="grid gap-6 lg:grid-cols-2">
            {/* Basic Information */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Key className="h-5 w-5" />
                  Basic Information
                </CardTitle>
                <CardDescription>
                  Basic details about your OAuth client application
                </CardDescription>
              </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="name">Name *</Label>
                  <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    placeholder="My Application"
                    aria-invalid={errors.name ? 'true' : 'false'}
                  />
                  {errors.name && (
                    <p className="text-sm text-destructive">{errors.name}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="environment">Environment *</Label>
                  <Select value={data.environment} onValueChange={(value) => setData('environment', value)}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select environment" />
                    </SelectTrigger>
                    <SelectContent>
                      {environments.map((env) => (
                        <SelectItem key={env} value={env}>
                          {env.charAt(0).toUpperCase() + env.slice(1)}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {errors.environment && (
                    <p className="text-sm text-destructive">{errors.environment}</p>
                  )}
                </div>
              </div>

              <div className="space-y-2">
                <Label htmlFor="description">Description</Label>
                <Textarea
                  id="description"
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  placeholder="A brief description of your application"
                  rows={3}
                />
                {errors.description && (
                  <p className="text-sm text-destructive">{errors.description}</p>
                )}
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="contact_email">Contact Email</Label>
                  <Input
                    id="contact_email"
                    type="email"
                    value={data.contact_email}
                    onChange={(e) => setData('contact_email', e.target.value)}
                    placeholder="admin@example.com"
                  />
                  {errors.contact_email && (
                    <p className="text-sm text-destructive">{errors.contact_email}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="website_url">Website URL</Label>
                  <Input
                    id="website_url"
                    type="url"
                    value={data.website_url}
                    onChange={(e) => setData('website_url', e.target.value)}
                    placeholder="https://example.com"
                  />
                  {errors.website_url && (
                    <p className="text-sm text-destructive">{errors.website_url}</p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

            {/* OAuth Configuration */}
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2">
                  <Settings className="h-5 w-5" />
                  OAuth Configuration
                </CardTitle>
                <CardDescription>
                  Configure OAuth 2.0 settings for your client
                </CardDescription>
              </CardHeader>
            <CardContent className="space-y-4">
              {/* Client Type */}
              <div className="flex items-center space-x-2">
                <Switch
                  id="is_confidential"
                  checked={data.is_confidential}
                  onCheckedChange={(checked) => setData('is_confidential', checked)}
                />
                <Label htmlFor="is_confidential">
                  Confidential Client
                  <span className="text-sm text-muted-foreground block">
                    {data.is_confidential 
                      ? 'Can securely store client secret (recommended for server applications)'
                      : 'Cannot securely store client secret (for mobile/SPA applications)'
                    }
                  </span>
                </Label>
              </div>

              {/* Redirect URIs */}
              <div className="space-y-2">
                <Label>Redirect URIs *</Label>
                <div className="flex gap-2">
                  <Input
                    value={redirectUriInput}
                    onChange={(e) => setRedirectUriInput(e.target.value)}
                    placeholder="https://example.com/auth/callback"
                    onKeyPress={(e) => e.key === 'Enter' && (e.preventDefault(), addRedirectUri())}
                  />
                  <Button type="button" onClick={addRedirectUri} size="sm">
                    <Plus className="h-4 w-4" />
                  </Button>
                </div>
                
                {data.redirect_uris.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {data.redirect_uris.map((uri) => (
                      <Badge key={uri} variant="secondary" className="pr-1">
                        {uri}
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          className="h-4 w-4 p-0 ml-1"
                          onClick={() => removeRedirectUri(uri)}
                        >
                          <X className="h-3 w-3" />
                        </Button>
                      </Badge>
                    ))}
                  </div>
                )}
                
                {errors.redirect_uris && (
                  <p className="text-sm text-destructive">{errors.redirect_uris}</p>
                )}
              </div>

              {/* Grants */}
              <div className="space-y-2">
                <Label>Allowed Grant Types *</Label>
                <div className="grid grid-cols-1 gap-2">
                  {grants.map((grant) => (
                    <div key={grant} className="flex items-center space-x-2">
                      <Checkbox
                        id={`grant-${grant}`}
                        checked={data.grants.includes(grant)}
                        onCheckedChange={(checked) => handleGrantChange(grant, checked as boolean)}
                      />
                      <Label htmlFor={`grant-${grant}`} className="text-sm">
                        {grant.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                      </Label>
                    </div>
                  ))}
                </div>
                {errors.grants && (
                  <p className="text-sm text-destructive">{errors.grants}</p>
                )}
              </div>

              {/* Scopes */}
              <div className="space-y-2">
                <Label>Allowed Scopes *</Label>
                <div className="grid grid-cols-1 gap-2">
                  {scopes.map((scope) => (
                    <div key={scope} className="flex items-center space-x-2">
                      <Checkbox
                        id={`scope-${scope}`}
                        checked={data.scopes.includes(scope)}
                        onCheckedChange={(checked) => handleScopeChange(scope, checked as boolean)}
                      />
                      <Label htmlFor={`scope-${scope}`} className="text-sm">
                        {scope}
                      </Label>
                    </div>
                  ))}
                </div>
                {errors.scopes && (
                  <p className="text-sm text-destructive">{errors.scopes}</p>
                )}
              </div>
            </CardContent>
          </Card>
          </div>

          {/* Security Settings */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Shield className="h-5 w-5" />
                Security Settings
              </CardTitle>
              <CardDescription>
                Configure rate limiting and token management
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label htmlFor="max_concurrent_tokens">Max Concurrent Tokens</Label>
                  <Input
                    id="max_concurrent_tokens"
                    type="number"
                    min="1"
                    value={data.max_concurrent_tokens}
                    onChange={(e) => setData('max_concurrent_tokens', parseInt(e.target.value) || 1000)}
                  />
                  {errors.max_concurrent_tokens && (
                    <p className="text-sm text-destructive">{errors.max_concurrent_tokens}</p>
                  )}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="rate_limit_per_minute">Rate Limit (per minute)</Label>
                  <Input
                    id="rate_limit_per_minute"
                    type="number"
                    min="1"
                    value={data.rate_limit_per_minute}
                    onChange={(e) => setData('rate_limit_per_minute', parseInt(e.target.value) || 100)}
                  />
                  {errors.rate_limit_per_minute && (
                    <p className="text-sm text-destructive">{errors.rate_limit_per_minute}</p>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Health Monitoring */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <Activity className="h-5 w-5" />
                Health Monitoring
              </CardTitle>
              <CardDescription>
                Configure health checks for your application
              </CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex items-center space-x-2">
                <Switch
                  id="health_check_enabled"
                  checked={data.health_check_enabled}
                  onCheckedChange={(checked) => setData('health_check_enabled', checked)}
                />
                <Label htmlFor="health_check_enabled">
                  Enable Health Checks
                  <span className="text-sm text-muted-foreground block">
                    Monitor your application's health status automatically
                  </span>
                </Label>
              </div>

              {data.health_check_enabled && (
                <div className="space-y-4 pl-6 border-l-2 border-muted">
                  <div className="space-y-2">
                    <Label htmlFor="health_check_url">Health Check URL *</Label>
                    <Input
                      id="health_check_url"
                      type="url"
                      value={data.health_check_url}
                      onChange={(e) => setData('health_check_url', e.target.value)}
                      placeholder="https://example.com/health"
                    />
                    {errors.health_check_url && (
                      <p className="text-sm text-destructive">{errors.health_check_url}</p>
                    )}
                    <p className="text-sm text-muted-foreground">
                      This endpoint should return HTTP 200 when your application is healthy
                    </p>
                  </div>

                  <div className="space-y-2">
                    <Label htmlFor="health_check_interval">Check Interval (seconds)</Label>
                    <Select 
                      value={data.health_check_interval.toString()} 
                      onValueChange={(value) => setData('health_check_interval', parseInt(value))}
                    >
                      <SelectTrigger>
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="60">1 minute</SelectItem>
                        <SelectItem value="300">5 minutes</SelectItem>
                        <SelectItem value="600">10 minutes</SelectItem>
                        <SelectItem value="900">15 minutes</SelectItem>
                        <SelectItem value="1800">30 minutes</SelectItem>
                      </SelectContent>
                    </Select>
                    {errors.health_check_interval && (
                      <p className="text-sm text-destructive">{errors.health_check_interval}</p>
                    )}
                  </div>
                </div>
              )}
            </CardContent>
          </Card>

          {/* Submit Actions */}
          <div className="flex items-center justify-between pt-6 border-t">
            <Link href={adminRoutes.oauthClients.show(client.id)}>
              <Button variant="outline" disabled={processing}>
                Cancel
              </Button>
            </Link>
            <Button 
              type="submit" 
              disabled={processing}
              className="flex items-center gap-2"
            >
              <Save className="h-4 w-4" />
              {processing ? 'Updating Client...' : 'Update OAuth Client'}
            </Button>
          </div>
        </form>
      </div>
    </AppLayout>
  )
}