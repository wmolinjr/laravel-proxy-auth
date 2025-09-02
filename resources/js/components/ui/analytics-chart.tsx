import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { TrendingUp, TrendingDown } from 'lucide-react';

interface DataPoint {
  date: string;
  value: number;
  label?: string;
}

interface AnalyticsChartProps {
  title: string;
  description?: string;
  data: DataPoint[];
  type?: 'line' | 'bar' | 'area';
  height?: number;
  showTrend?: boolean;
  valueFormatter?: (value: number) => string;
  className?: string;
}

export function AnalyticsChart({
  title,
  description,
  data,
  type = 'line',
  height = 200,
  showTrend = true,
  valueFormatter = (value) => value.toString(),
  className,
}: AnalyticsChartProps) {
  if (data.length === 0) {
    return (
      <Card className={className}>
        <CardHeader>
          <CardTitle>{title}</CardTitle>
          {description && <CardDescription>{description}</CardDescription>}
        </CardHeader>
        <CardContent>
          <div className="flex items-center justify-center h-32 text-muted-foreground">
            No data available
          </div>
        </CardContent>
      </Card>
    );
  }

  const maxValue = Math.max(...data.map(d => d.value));
  const minValue = Math.min(...data.map(d => d.value));
  const range = maxValue - minValue || 1;

  // Calculate trend
  const firstValue = data[0]?.value || 0;
  const lastValue = data[data.length - 1]?.value || 0;
  const trendPercentage = firstValue === 0 ? 0 : ((lastValue - firstValue) / firstValue) * 100;
  const isPositiveTrend = trendPercentage > 0;

  // Generate SVG path for line chart
  const generatePath = () => {
    const width = 100; // percentage
    const chartHeight = 100; // percentage
    
    const points = data.map((point, index) => {
      const x = (index / (data.length - 1)) * width;
      const y = chartHeight - ((point.value - minValue) / range) * chartHeight;
      return `${x},${y}`;
    }).join(' L');

    return `M${points}`;
  };

  const generateBars = () => {
    return data.map((point, index) => {
      const width = 80 / data.length; // percentage
      const x = (index / data.length) * 100 + (100 - 80) / 2; // center bars
      const height = ((point.value - minValue) / range) * 80; // percentage
      const y = 90 - height; // from bottom

      return (
        <rect
          key={index}
          x={`${x}%`}
          y={`${y}%`}
          width={`${width * 0.8}%`}
          height={`${height}%`}
          className="fill-primary/80"
          rx="2"
        />
      );
    });
  };

  return (
    <Card className={className}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle>{title}</CardTitle>
            {description && <CardDescription>{description}</CardDescription>}
          </div>
          {showTrend && (
            <div className={cn(
              'flex items-center gap-1 text-sm',
              isPositiveTrend ? 'text-green-600' : 'text-red-600'
            )}>
              {isPositiveTrend ? <TrendingUp className="h-4 w-4" /> : <TrendingDown className="h-4 w-4" />}
              {Math.abs(trendPercentage).toFixed(1)}%
            </div>
          )}
        </div>
      </CardHeader>
      <CardContent>
        <div className="space-y-2">
          {/* Chart */}
          <div className="relative" style={{ height: `${height}px` }}>
            <svg
              className="w-full h-full"
              viewBox="0 0 100 100"
              preserveAspectRatio="none"
            >
              {/* Grid lines */}
              <defs>
                <pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse">
                  <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" strokeWidth="0.5" className="text-muted-foreground/20" />
                </pattern>
              </defs>
              <rect width="100%" height="100%" fill="url(#grid)" />
              
              {/* Chart content */}
              {type === 'line' && (
                <>
                  <path
                    d={generatePath()}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    className="text-primary"
                  />
                  {data.map((point, index) => {
                    const x = (index / (data.length - 1)) * 100;
                    const y = 100 - ((point.value - minValue) / range) * 100;
                    return (
                      <circle
                        key={index}
                        cx={`${x}%`}
                        cy={`${y}%`}
                        r="2"
                        className="fill-primary"
                      />
                    );
                  })}
                </>
              )}
              
              {type === 'area' && (
                <path
                  d={`${generatePath()} L100,100 L0,100 Z`}
                  className="fill-primary/20 stroke-primary"
                  strokeWidth="2"
                />
              )}
              
              {type === 'bar' && generateBars()}
            </svg>

            {/* Hover tooltips would go here in a real implementation */}
          </div>

          {/* Data points summary */}
          <div className="flex items-center justify-between text-xs text-muted-foreground">
            <span>{data[0]?.date}</span>
            <div className="flex gap-4">
              <span>Min: {valueFormatter(minValue)}</span>
              <span>Max: {valueFormatter(maxValue)}</span>
              <span>Avg: {valueFormatter(data.reduce((sum, d) => sum + d.value, 0) / data.length)}</span>
            </div>
            <span>{data[data.length - 1]?.date}</span>
          </div>
        </div>
      </CardContent>
    </Card>
  );
}

interface MetricTrendProps {
  current: number;
  previous: number;
  label: string;
  formatter?: (value: number) => string;
  className?: string;
}

export function MetricTrend({ 
  current, 
  previous, 
  label, 
  formatter = (v) => v.toString(),
  className 
}: MetricTrendProps) {
  const change = current - previous;
  const percentageChange = previous === 0 ? 0 : (change / previous) * 100;
  const isPositive = change > 0;

  return (
    <div className={cn('flex items-center justify-between', className)}>
      <div>
        <div className="text-2xl font-bold">{formatter(current)}</div>
        <div className="text-sm text-muted-foreground">{label}</div>
      </div>
      <div className={cn(
        'flex items-center gap-1 text-sm',
        isPositive ? 'text-green-600' : change < 0 ? 'text-red-600' : 'text-muted-foreground'
      )}>
        {change !== 0 && (
          <>
            {isPositive ? <TrendingUp className="h-4 w-4" /> : <TrendingDown className="h-4 w-4" />}
            <span>{isPositive ? '+' : ''}{percentageChange.toFixed(1)}%</span>
          </>
        )}
      </div>
    </div>
  );
}

// Simple sparkline component for inline metrics
export function Sparkline({ 
  data, 
  className,
  color = 'text-primary' 
}: { 
  data: number[]; 
  className?: string;
  color?: string;
}) {
  if (data.length < 2) return null;

  const max = Math.max(...data);
  const min = Math.min(...data);
  const range = max - min || 1;

  const points = data.map((value, index) => {
    const x = (index / (data.length - 1)) * 100;
    const y = 100 - ((value - min) / range) * 100;
    return `${x},${y}`;
  }).join(' ');

  return (
    <svg 
      className={cn('w-16 h-6', className)} 
      viewBox="0 0 100 100" 
      preserveAspectRatio="none"
    >
      <polyline
        fill="none"
        stroke="currentColor"
        strokeWidth="3"
        points={points}
        className={color}
      />
    </svg>
  );
}