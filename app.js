App({
  onLaunch() {
    // 初始化：可在此做登录/取缓存 user
    const user = wx.getStorageSync('user') || null
    if (!user) {
      // optional: try wx.login -> backend
    }
  }
})
