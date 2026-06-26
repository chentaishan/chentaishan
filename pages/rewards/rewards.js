const api = require('../../utils/api.js');
Page({
  data: { balance: '0.00', rewards: [] },
  async onLoad() {
    try {
      const res = await api.getRewards();
      this.setData({ balance: res.balance || '0.00', rewards: res.list || [] });
      api.reportEvent('rewards_view', {});
    } catch (e) {}
  },
  async claim(e) {
    const id = e.currentTarget.dataset.id;
    try {
      await api.claimReward(id);
      wx.showToast({ title: '领取成功', icon: 'success' });
      api.reportEvent('claim_reward', { id });
      // 刷新
      const res = await api.getRewards();
      this.setData({ balance: res.balance || '0.00', rewards: res.list || [] });
    } catch (err) {
      wx.showToast({ title: '领取失败', icon: 'none' });
    }
  }
})
