(function () {
  if (window.Chart) {
    return;
  }
  function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  }
  class SimpleChart {
    constructor(ctx, config) {
      this.ctx = ctx;
      this.config = config;
      this.canvas = ctx.canvas;
      this.draw();
    }
    update() {
      this.draw();
    }
    clear() {
      const { width, height } = this.canvas;
      this.ctx.clearRect(0, 0, width, height);
      this.ctx.fillStyle = '#ffffff';
      this.ctx.fillRect(0, 0, width, height);
    }
    draw() {
      this.clear();
      const type = this.config.type;
      if (type === 'line') {
        this.drawLine();
      } else if (type === 'doughnut') {
        this.drawDoughnut();
      } else {
        this.drawFallback();
      }
    }
    drawLine() {
      const ctx = this.ctx;
      const { width, height } = ctx.canvas;
      const labels = this.config.data.labels || [];
      const dataset = (this.config.data.datasets || [])[0] || { data: [] };
      const data = dataset.data.map((v) => Number(v) || 0);
      const max = Math.max(...data, 1);
      const padding = 40;
      const chartWidth = width - padding * 2;
      const chartHeight = height - padding * 2;
      ctx.save();
      ctx.translate(padding, height - padding);
      ctx.strokeStyle = '#94a3b8';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(0, 0);
      ctx.lineTo(0, -chartHeight);
      ctx.lineTo(chartWidth, -chartHeight);
      ctx.stroke();
      ctx.strokeStyle = dataset.borderColor || '#6366f1';
      ctx.fillStyle = dataset.backgroundColor || 'rgba(99,102,241,0.2)';
      ctx.lineWidth = 2;
      ctx.beginPath();
      data.forEach((value, index) => {
        const x = (index / Math.max(data.length - 1, 1)) * chartWidth;
        const y = -(value / max) * chartHeight;
        if (index === 0) {
          ctx.moveTo(x, y);
        } else {
          ctx.lineTo(x, y);
        }
      });
      ctx.stroke();
      ctx.lineTo(chartWidth, 0);
      ctx.lineTo(0, 0);
      ctx.closePath();
      ctx.globalAlpha = 0.2;
      ctx.fill();
      ctx.globalAlpha = 1;
      ctx.fillStyle = '#475569';
      ctx.font = '10px sans-serif';
      labels.forEach((label, index) => {
        const x = (index / Math.max(labels.length - 1, 1)) * chartWidth;
        ctx.save();
        ctx.translate(x, 16);
        ctx.rotate(-Math.PI / 6);
        ctx.fillText(label, 0, 0);
        ctx.restore();
      });
      ctx.restore();
    }
    drawDoughnut() {
      const ctx = this.ctx;
      const { width, height } = ctx.canvas;
      const labels = this.config.data.labels || [];
      const dataset = (this.config.data.datasets || [])[0] || { data: [] };
      const data = dataset.data.map((v) => Math.max(Number(v) || 0, 0));
      const sum = data.reduce((acc, cur) => acc + cur, 0) || 1;
      const colors = dataset.backgroundColor || ['#6366f1', '#22d3ee', '#f97316', '#14b8a6', '#f43f5e', '#8b5cf6'];
      const centerX = width / 2;
      const centerY = height / 2;
      const radius = Math.min(width, height) / 2 - 20;
      const inner = radius * 0.6;
      let start = -Math.PI / 2;
      data.forEach((value, index) => {
        const angle = (value / sum) * Math.PI * 2;
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.fillStyle = colors[index % colors.length];
        ctx.arc(centerX, centerY, radius, start, start + angle);
        ctx.lineTo(centerX, centerY);
        ctx.fill();
        start += angle;
      });
      ctx.globalCompositeOperation = 'destination-out';
      ctx.beginPath();
      ctx.arc(centerX, centerY, inner, 0, Math.PI * 2);
      ctx.fill();
      ctx.globalCompositeOperation = 'source-over';
      ctx.fillStyle = '#0f172a';
      ctx.font = '12px sans-serif';
      labels.forEach((label, index) => {
        const value = data[index];
        const angle = ((value / sum) * Math.PI * 2) / 2;
        const mid = startAngleForIndex(data, index) + angle;
        const tx = centerX + Math.cos(mid) * (radius + 20);
        const ty = centerY + Math.sin(mid) * (radius + 20);
        ctx.fillText(label, tx, ty);
      });
    }
    drawFallback() {
      this.ctx.fillStyle = '#64748b';
      this.ctx.font = '14px sans-serif';
      this.ctx.fillText('Chart.js fallback active', 10, 20);
    }
  }
  function startAngleForIndex(data, index) {
    const sum = data.reduce((acc, cur) => acc + cur, 0) || 1;
    let angle = -Math.PI / 2;
    for (let i = 0; i < index; i += 1) {
      angle += (data[i] / sum) * Math.PI * 2;
    }
    return angle;
  }
  window.Chart = class {
    constructor(ctx, config) {
      this._chart = new SimpleChart(ctx, config);
      this.config = config;
      this.data = config.data;
    }
    update() {
      this._chart.config = { type: this.config.type, data: this.data };
      this._chart.draw();
    }
  };
})();
